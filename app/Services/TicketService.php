<?php

namespace App\Services;

use App\Models\Project;
use App\Models\Ticket;
use App\Models\User;
use App\Policies\TicketPolicy;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;

class TicketService
{
    public function __construct(private AccountService $accounts, private ProjectService $projects) {}

    public function lock(User $actor, Project $project, string $permission): array
    {
        $this->accounts->lockAdministration();
        $actor = $this->projects->currentActor($actor);
        $project = Project::whereKey($project->id)->lockForUpdate()->firstOrFail();
        if (! (new TicketPolicy)->access($actor, $project, $permission)) {
            throw new AuthorizationException;
        }
        if ($project->archived_at) {
            $this->invalid('project', 'Restore this project before making changes.');
        }

        return [$actor, $project];
    }

    public function create(User $actor, Project $project, array $values): Ticket
    {
        return DB::transaction(function () use ($actor, $project, $values) {
            [$actor, $project] = $this->lock($actor, $project, 'create');
            $values = $this->validate($project, $values);
            $counter = DB::table('project_counters')->where('project_id', $project->id)->lockForUpdate()->firstOrFail();
            $number = $counter->next_number;
            DB::table('project_counters')->where('project_id', $project->id)->increment('next_number');
            $workflow = app(WorkflowService::class)->current($project);
            $state = $workflow->definition['initial'];
            $rank = ($project->tickets()->where('state', $state)->max('board_rank') ?? 0) + 1;
            $ticket = $project->tickets()->create([...$values, 'number' => $number, 'key' => $project->key.'-'.str_pad((string) $number, 5, '0', STR_PAD_LEFT), 'reporter_id' => $actor->id, 'workflow_version_id' => $workflow->id, 'state' => $state, 'board_rank' => $rank, 'revision' => 1]);
            $project->increment('board_revision');
            $ticket->events()->create(['actor_id' => $actor->id, 'action' => 'created', 'changes' => ['before' => null, 'after' => $this->snapshot($ticket)], 'created_at' => now()]);

            return $ticket;
        }, 3);
    }

    public function update(User $actor, Ticket $ticket, int $revision, array $values): Ticket
    {
        return DB::transaction(function () use ($actor, $ticket, $revision, $values) {
            [$actor, $project] = $this->lock($actor, $ticket->project, 'update');
            $ticket = $project->tickets()->whereKey($ticket->id)->lockForUpdate()->firstOrFail();
            if ($ticket->revision !== $revision) {
                $this->invalid('revision', 'This ticket changed. Reload before saving again.');
            }
            $values = $this->validate($project, $values, $ticket);
            $before = $this->snapshot($ticket);
            $ticket->fill($values);
            if (! $ticket->isDirty()) {
                $this->invalid('ticket', 'No ticket fields have changed.');
            }
            $after = $this->snapshot($ticket);
            $changed = array_keys(array_filter($after, fn ($value, $key) => $value !== $before[$key], ARRAY_FILTER_USE_BOTH));
            $ticket->revision++;
            $ticket->save();
            $ticket->events()->create(['actor_id' => $actor->id, 'action' => 'updated', 'changes' => ['before' => array_intersect_key($before, array_flip($changed)), 'after' => array_intersect_key($after, array_flip($changed))], 'created_at' => now()]);

            return $ticket;
        }, 3);
    }

    private function validate(Project $project, array $values, ?Ticket $ticket = null): array
    {
        if (isset($values['summary']) && is_string($values['summary'])) {
            $values['summary'] = trim($values['summary']);
        }
        $rules = ['summary' => ['required', 'string', 'max:255'], 'description' => ['nullable', 'string', 'max:50000'], 'acceptance_criteria' => ['nullable', 'string', 'max:20000'], 'work_type_id' => ['required', 'integer'], 'priority_id' => ['required', 'integer'], 'assignee_id' => ['nullable', 'integer', 'min:1'], 'estimate' => ['nullable', 'numeric', 'min:0', 'max:9999.99', 'decimal:0,2'], 'due_date' => ['nullable', 'date_format:Y-m-d']];
        $rules['start_date'] = ['nullable', 'date_format:Y-m-d'];
        if ($ticket) {
            $values = [...['start_date' => $ticket->start_date?->format('Y-m-d'), 'due_date' => $ticket->due_date?->format('Y-m-d')], ...$values];
        }
        $start = $values['start_date'] ?? null;
        if ($start) {
            $rules['due_date'][] = 'after_or_equal:'.$start;
        }
        foreach (['project_id', 'key', 'number', 'reporter_id', 'state', 'status', 'resolution', 'resolution_id', 'resolved_at', 'completed_at', 'workflow_id', 'workflow_version_id', 'workflow_status_id', 'board_rank', 'revision'] as $field) {
            $rules[$field] = ['missing'];
        }
        $data = Validator::make($values, $rules)->validate();
        foreach (['work_type_id' => 'work_types', 'priority_id' => 'priorities'] as $field => $table) {
            $record = DB::table($table)->where('project_id', $project->id)->where('id', $data[$field])->first();
            if (! $record || (! $record->is_active && (int) $data[$field] !== $ticket?->$field)) {
                $this->invalid($field, 'Choose an active option from this project.');
            }
        }
        $assignee = isset($data['assignee_id']) ? (int) $data['assignee_id'] : null;
        if ($assignee && $ticket?->assignee_id !== $assignee && ! $this->eligibleAssignee($project, $assignee)) {
            $this->invalid('assignee_id', 'Choose an active member with project access.');
        }

        return [...$data, 'assignee_id' => $assignee];
    }

    public function eligibleAssignee(Project $project, int $id): bool
    {
        $user = User::find($id);

        return $user && $user->is_active && $project->members()->where('user_id', $id)->whereNull('removed_at')->exists() && $project->allows($user, 'project.view');
    }

    private function snapshot(Ticket $ticket): array
    {
        $values = $ticket->only(['key', 'summary', 'description', 'acceptance_criteria', 'work_type_id', 'priority_id', 'reporter_id', 'assignee_id', 'estimate', 'state', 'workflow_version_id']);
        $values['start_date'] = $ticket->start_date?->format('Y-m-d');
        $values['due_date'] = $ticket->due_date?->format('Y-m-d');
        $values['work_type'] = DB::table('work_types')->where('id', $ticket->work_type_id)->value('name');
        $values['priority'] = DB::table('priorities')->where('id', $ticket->priority_id)->value('name');
        $values['assignee'] = $ticket->assignee_id ? User::find($ticket->assignee_id)?->name : null;

        return $values;
    }

    private function invalid(string $field, string $message): never
    {
        throw ValidationException::withMessages([$field => $message]);
    }
}

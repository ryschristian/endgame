<?php

namespace App\Services;

use App\Models\Project;
use App\Models\Ticket;
use App\Models\User;
use App\Models\WorkflowVersion;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class WorkflowService
{
    public function __construct(private TicketService $tickets, private ProjectService $projects, private AccountService $accounts) {}

    public function current(Project $project): WorkflowVersion
    {
        return WorkflowVersion::where('project_id', $project->id)->orderByDesc('version')->firstOrFail();
    }

    public function publish(User $actor, Project $project, int $versionId, array $definition, array $mapping): WorkflowVersion
    {
        return DB::transaction(function () use ($actor, $project, $versionId, $definition, $mapping) {
            $this->accounts->lockAdministration();
            $actor = $this->projects->currentActor($actor);
            $project = Project::whereKey($project->id)->lockForUpdate()->firstOrFail();
            abort_unless($project->allows($actor, 'project.view') && $project->allows($actor, 'project.update'), 403);
            if ($project->archived_at) {
                $this->invalid('project', 'Restore this project before publishing a workflow.');
            }
            $old = $this->current($project);
            if ($old->id !== $versionId) {
                $this->invalid('workflow', 'The workflow changed. Reload before publishing.');
            }
            $definition = $this->validateDefinition($definition);
            $statuses = collect($definition['statuses'])->keyBy('key');
            foreach ($old->definition['statuses'] as $status) {
                $target = $statuses->get($mapping[$status['key']] ?? '');
                if (! $target || $target['completed'] !== $status['completed']) {
                    $this->invalid('mapping', 'Map every existing stage to a stage with the same completion category.');
                }
            }
            $new = WorkflowVersion::create(['project_id' => $project->id, 'version' => $old->version + 1, 'definition' => $definition, 'published_by' => $actor->id]);
            $project->tickets()->orderBy('id')->chunkById(100, function ($tickets) use ($actor, $new, $mapping, $old, $statuses) {
                foreach ($tickets as $ticket) {
                    $before = ['workflow_version_id' => $ticket->workflow_version_id, 'state' => $ticket->state, 'status' => collect($old->definition['statuses'])->firstWhere('key', $ticket->state)['label']];
                    $ticket->update(['workflow_version_id' => $new->id, 'state' => $mapping[$ticket->state], 'revision' => $ticket->revision + 1]);
                    $this->event($ticket, $actor, 'workflow_migrated', ['before' => $before, 'after' => ['workflow_version_id' => $new->id, 'state' => $ticket->state, 'status' => $statuses[$ticket->state]['label']]]);
                }
            });
            $project->increment('board_revision');
            $this->projects->audit($actor, $project, 'project.workflow_published', ['before_version_id' => $old->id, 'after_version_id' => $new->id, 'mapping' => $mapping]);

            return $new;
        }, 3);
    }

    private function validateDefinition(array $definition): array
    {
        $data = Validator::make($definition, [
            'initial' => ['required', 'string'],
            'statuses' => ['required', 'array', 'min:2', 'max:20'],
            'statuses.*.key' => ['required', 'regex:/^[a-z][a-z0-9_]{0,29}$/D', 'distinct'],
            'statuses.*.label' => ['required', 'string', 'max:60', 'distinct:ignore_case'],
            'statuses.*.color' => ['required', 'regex:/^#[0-9a-fA-F]{6}$/D'],
            'statuses.*.completed' => ['required', 'boolean'],
            'transitions' => ['required', 'array', 'min:1', 'max:380'],
            'transitions.*.from' => ['required', 'string'], 'transitions.*.to' => ['required', 'string'],
            'resolutions' => ['required', 'array', 'min:1', 'max:20'],
            'resolutions.*.key' => ['required', 'regex:/^[a-z][a-z0-9_]{0,39}$/D', 'distinct'],
            'resolutions.*.label' => ['required', 'string', 'max:60', 'distinct:ignore_case'],
        ])->validate();
        $data['statuses'] = array_map(fn ($s) => [...$s, 'label' => trim($s['label']), 'completed' => (bool) $s['completed']], $data['statuses']);
        $statuses = collect($data['statuses'])->keyBy('key');
        if (! $statuses->has($data['initial']) || $statuses[$data['initial']]['completed'] || ! $statuses->contains('completed', true)) {
            $this->invalid('initial', 'Choose an active initial stage and at least one completed stage.');
        }
        $edges = [];
        foreach ($data['transitions'] as $edge) {
            $key = $edge['from'].'>'.$edge['to'];
            if (! $statuses->has($edge['from']) || ! $statuses->has($edge['to']) || $edge['from'] === $edge['to'] || in_array($key, $edges, true)) {
                $this->invalid('transitions', 'Transitions must connect different stages in this workflow, without duplicates.');
            }
            $edges[] = $key;
        }
        // Every stage must be reachable from the initial stage; otherwise tickets can be stranded.
        $reachable = [$data['initial']];
        do {
            $before = count($reachable);
            foreach ($data['transitions'] as $edge) {
                if (in_array($edge['from'], $reachable, true) && ! in_array($edge['to'], $reachable, true)) {
                    $reachable[] = $edge['to'];
                }
            }
        } while ($before !== count($reachable));
        if (count($reachable) !== $statuses->count()) {
            $this->invalid('transitions', 'Every stage must be reachable from the initial stage.');
        }

        return $data;
    }

    public function transition(User $actor, Ticket $ticket, int $revision, string $target, ?string $resolution = null, ?string $reason = null): void
    {
        DB::transaction(function () use ($actor, $ticket, $revision, $target, $resolution, $reason) {
            [$actor, $project] = $this->tickets->lock($actor, $ticket->project, 'update');
            $ticket = $this->lockedTicket($project, $ticket, $revision);
            $this->applyTransition($actor, $ticket, $target, $resolution, $reason);
            $ticket->board_rank = ($project->tickets()->where('state', $target)->max('board_rank') ?? 0) + 1;
            $ticket->revision++;
            $ticket->save();
            $project->increment('board_revision');
        }, 3);
    }

    private function applyTransition(User $actor, Ticket $ticket, string $target, ?string $resolution, ?string $reason): void
    {
        $definition = $ticket->workflowVersion->definition;
        $statuses = collect($definition['statuses'])->keyBy('key');
        $allowed = collect($definition['transitions'])->contains(fn ($edge) => $edge['from'] === $ticket->state && $edge['to'] === $target);
        if (! $allowed || ! $statuses->has($target)) {
            $this->invalid('state', 'This transition is not allowed by the ticket workflow.');
        }
        $from = $statuses[$ticket->state];
        $to = $statuses[$target];
        if ($to['completed']) {
            Validator::make(['resolution' => $resolution], ['resolution' => ['required', Rule::in(array_column($definition['resolutions'], 'key'))]])->validate();
        }
        if ($from['completed'] && ! $to['completed']) {
            Validator::make(['reason' => $reason], ['reason' => ['required', 'string', 'max:2000', 'not_regex:/^\s*$/']])->validate();
        }
        $before = ['state' => $ticket->state, 'status' => $from['label'], 'workflow_version_id' => $ticket->workflow_version_id, 'resolution' => $ticket->resolution, 'completed_at' => $ticket->completed_at?->toISOString()];
        $ticket->state = $target;
        $ticket->resolution = $to['completed'] ? $resolution : null;
        $ticket->completed_at = $to['completed'] ? now() : null;
        $this->event($ticket, $actor, 'transitioned', ['before' => $before, 'after' => ['state' => $target, 'status' => $to['label'], 'workflow_version_id' => $ticket->workflow_version_id, 'resolution' => $ticket->resolution, 'completed_at' => $ticket->completed_at?->toISOString()], 'reason' => $reason]);
    }

    public function move(User $actor, Ticket $ticket, int $revision, string $target, ?int $beforeId, int $boardRevision, ?string $resolution = null, ?string $reason = null): void
    {
        DB::transaction(function () use ($actor, $ticket, $revision, $target, $beforeId, $boardRevision, $resolution, $reason) {
            [$actor, $project] = $this->tickets->lock($actor, $ticket->project, 'update');
            if ($project->board_revision !== $boardRevision) {
                $this->invalid('board', 'The board changed. Reload before moving this ticket.');
            }
            $ticket = $this->lockedTicket($project, $ticket, $revision);
            $anchor = $beforeId ? $project->tickets()->where('state', $target)->whereKeyNot($ticket->id)->find($beforeId) : null;
            if ($beforeId && ! $anchor) {
                $this->invalid('board', 'Choose a ticket in the destination column of this project.');
            }
            $oldRank = $ticket->board_rank;
            if ($ticket->state !== $target) {
                $this->applyTransition($actor, $ticket, $target, $resolution, $reason);
            }
            $ids = $project->tickets()->where('state', $target)->whereKeyNot($ticket->id)->orderBy('board_rank')->orderBy('id')->pluck('id')->all();
            $index = $beforeId ? array_search($beforeId, $ids, true) : count($ids);
            array_splice($ids, $index, 0, [$ticket->id]);
            foreach ($ids as $rank => $id) {
                if ($id === $ticket->id) {
                    $ticket->board_rank = $rank + 1;
                } else {
                    $project->tickets()->whereKey($id)->update(['board_rank' => $rank + 1]);
                }
            }
            $ticket->revision++;
            $ticket->save();
            $project->increment('board_revision');
            $this->event($ticket, $actor, 'ordered', ['before' => ['board_rank' => $oldRank], 'after' => ['board_rank' => $ticket->board_rank, 'before_ticket_id' => $beforeId]]);
        }, 3);
    }

    public function schedule(User $actor, Ticket $ticket, int $revision, ?string $start, ?string $due): void
    {
        DB::transaction(function () use ($actor, $ticket, $revision, $start, $due) {
            [$actor, $project] = $this->tickets->lock($actor, $ticket->project, 'update');
            $ticket = $this->lockedTicket($project, $ticket, $revision);
            $values = Validator::make(['start_date' => $start, 'due_date' => $due], ['start_date' => ['nullable', 'date_format:Y-m-d'], 'due_date' => ['nullable', 'date_format:Y-m-d', ...($start ? ['after_or_equal:start_date'] : [])]])->validate();
            $before = ['start_date' => $ticket->start_date?->format('Y-m-d'), 'due_date' => $ticket->due_date?->format('Y-m-d')];
            if ($values === $before) {
                $this->invalid('schedule', 'The dates have not changed.');
            }
            $ticket->update([...$values, 'revision' => $ticket->revision + 1]);
            $this->event($ticket, $actor, 'scheduled', ['before' => $before, 'after' => $values]);
        }, 3);
    }

    private function lockedTicket(Project $project, Ticket $ticket, int $revision): Ticket
    {
        $ticket = $project->tickets()->whereKey($ticket->id)->lockForUpdate()->firstOrFail();
        if ($ticket->revision !== $revision) {
            $this->invalid('revision', 'This ticket changed. Reload before saving again.');
        }

        return $ticket;
    }

    private function event(Ticket $ticket, User $actor, string $action, array $changes): void
    {
        $ticket->events()->create(['actor_id' => $actor->id, 'action' => $action, 'changes' => $changes, 'created_at' => now()]);
    }

    private function invalid(string $field, string $message): never
    {
        throw ValidationException::withMessages([$field => $message]);
    }
}

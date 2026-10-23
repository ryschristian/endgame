<?php

namespace App\Support;

use App\Models\Project;
use App\Models\Ticket;
use App\Models\User;
use App\Policies\TicketPolicy;
use App\Services\TicketService;
use Illuminate\Support\Facades\DB;

class TicketPageData
{
    public const LABELS = ['summary' => 'Summary', 'description' => 'Description', 'work_type_id' => 'Type', 'priority_id' => 'Priority', 'reporter_id' => 'Reporter', 'assignee_id' => 'Assignee', 'acceptance_criteria' => 'Acceptance criteria', 'estimate' => 'Estimate (hours)', 'due_date' => 'Due date', 'state' => 'Status'];

    public static function authorize(User $user, Project $project, string $ability = 'view'): void
    {
        abort_unless((new TicketPolicy)->access($user, $project), 404);
        abort_unless((new TicketPolicy)->access($user, $project, $ability), 403);
    }

    public static function row(Ticket $ticket, bool $details = false): array
    {
        $data = [...$ticket->only(['id', 'project_id', 'number', 'key', 'summary', 'work_type_id', 'priority_id', 'assignee_id', 'estimate', 'revision']), 'due_date' => $ticket->due_date?->format('Y-m-d'), 'updated_at' => $ticket->updated_at->toISOString(), 'stateLabel' => 'Open', 'workType' => $ticket->workType->only(['id', 'name']), 'priority' => $ticket->priority->only(['id', 'name']), 'assignee' => $ticket->assignee?->only(['id', 'name'])];
        if ($details) {
            $data = [...$data, ...$ticket->only(['description', 'acceptance_criteria']), 'reporter' => $ticket->reporter->only(['id', 'name']), 'assigneeAvailable' => ! $ticket->assignee_id || app(TicketService::class)->eligibleAssignee($ticket->project, $ticket->assignee_id)];
        }

        return $data;
    }

    public static function shared(User $user, Project $project, ?Ticket $ticket = null): array
    {
        $policy = new TicketPolicy;
        $can = [];
        foreach (['view', 'create', 'update', 'configure'] as $ability) {
            $can[$ability] = $policy->access($user, $project, $ability);
        }
        $catalogs = [];
        $options = [];
        foreach (['workTypes' => 'work_types', 'priorities' => 'priorities'] as $key => $table) {
            $records = DB::table($table)->where('project_id', $project->id)->orderBy('position')->orderBy('id')->get();
            $catalogs[$key] = $can['configure'] ? $records : [];
            $selected = $key === 'workTypes' ? $ticket?->work_type_id : $ticket?->priority_id;
            $options[$key] = $records->filter(fn ($r) => $r->is_active || $r->id === $selected)->map(fn ($r) => ['value' => $r->id, 'label' => $r->name, 'disabled' => ! $r->is_active, 'default' => (bool) $r->is_default])->values();
        }

        return [...ProjectPageData::shared($user, $project), 'ticketCan' => $can, 'fieldLabels' => self::LABELS, 'options' => $options, 'catalogs' => $catalogs, 'catalogRevision' => $project->ticket_catalog_revision, 'defaults' => ['work_type_id' => $options['workTypes']->firstWhere('default', true)['value'] ?? null, 'priority_id' => $options['priorities']->firstWhere('default', true)['value'] ?? null], 'selectedTicket' => $ticket ? self::row($ticket, true) : null, 'history' => $ticket?->events()->with('actor:id,name')->latest('id')->paginate(25, ['*'], 'history_page')->withQueryString()->through(fn ($event) => ['id' => $event->id, 'actor' => $event->actor->name, 'action' => $event->action, 'changes' => $event->changes, 'created_at' => $event->created_at->toISOString()])];
    }
}

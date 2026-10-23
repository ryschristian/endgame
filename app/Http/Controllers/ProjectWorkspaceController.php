<?php

namespace App\Http\Controllers;

use App\Models\Project;
use App\Models\TicketEvent;
use App\Services\WorkflowService;
use App\Support\TicketPageData;
use Carbon\CarbonImmutable;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

class ProjectWorkspaceController extends Controller
{
    public function index(Request $request, Project $project, string $view = 'board'): Response
    {
        abort_unless(in_array($view, ['board', 'list', 'table', 'calendar', 'timeline'], true), 404);
        TicketPageData::authorize($request->user(), $project);
        $workflow = app(WorkflowService::class)->current($project);
        $sortOptions = [['value' => 'manual', 'label' => 'Manual'], ['value' => 'summary', 'label' => 'Name'], ['value' => 'due_date', 'label' => 'Due date'], ['value' => 'priority', 'label' => 'Priority'], ['value' => 'updated_at', 'label' => 'Updated']];
        $filters = $request->validate([
            'q' => ['nullable', 'string', 'max:255'], 'state' => ['nullable', Rule::in(array_column($workflow->definition['statuses'], 'key'))],
            'work_type_id' => ['nullable', 'integer', Rule::exists('work_types', 'id')->where('project_id', $project->id)],
            'priority_id' => ['nullable', 'integer', Rule::exists('priorities', 'id')->where('project_id', $project->id)],
            'assignee_id' => ['nullable', 'regex:/^(unassigned|[0-9]+)$/'],
            'sort' => ['nullable', Rule::in(array_column($sortOptions, 'value'))], 'direction' => ['nullable', Rule::in(['asc', 'desc'])],
            'month' => ['nullable', 'date_format:Y-m'], 'ticket' => ['nullable', 'integer'],
        ]);
        $filters = [...array_fill_keys(['q', 'state', 'work_type_id', 'priority_id', 'assignee_id'], ''), 'sort' => 'manual', 'direction' => 'asc', 'month' => now(Project::TEAM_TIMEZONE)->format('Y-m'), ...$filters];
        $query = $project->tickets()->with(['workType', 'priority', 'assignee', 'workflowVersion'])
            ->when($filters['q'], fn ($q, $term) => $q->where(fn ($q) => $q->where('summary', 'like', '%'.$term.'%')->orWhere('key', 'like', '%'.$term.'%')))
            ->when($filters['state'], fn ($q, $state) => $q->where('state', $state))
            ->when($filters['work_type_id'], fn ($q, $id) => $q->where('work_type_id', $id))
            ->when($filters['priority_id'], fn ($q, $id) => $q->where('priority_id', $id));
        $selectedAssignee = null;
        if ($filters['assignee_id'] === 'unassigned') {
            $query->whereNull('assignee_id');
        } elseif ($filters['assignee_id']) {
            $member = $project->members()->with('user:id,name')->where('user_id', $filters['assignee_id'])->firstOrFail();
            $selectedAssignee = $member->user->only(['id', 'name']);
            $query->where('assignee_id', $member->user_id);
        }
        $counts = (clone $query)->reorder()->selectRaw('state, count(*) as total')->groupBy('state')->pluck('total', 'state');
        if (in_array($view, ['calendar', 'timeline'], true)) {
            $start = CarbonImmutable::createFromFormat('!Y-m', $filters['month'], Project::TEAM_TIMEZONE)->startOfMonth()->startOfWeek();
            $end = $start->addDays(41);
            $query->where(fn ($q) => $q->whereNull('due_date')->orWhere(fn ($q) => $q->whereDate('due_date', '>=', $start->format('Y-m-d'))->whereDate($view === 'timeline' ? 'start_date' : 'due_date', '<=', $end->format('Y-m-d')))->orWhere(fn ($q) => $q->whereNull('start_date')->whereBetween('due_date', [$start->format('Y-m-d'), $end->format('Y-m-d')])));
        }
        if ($filters['sort'] === 'priority') {
            $query->orderByRaw('(select position from priorities where priorities.id = tickets.priority_id) '.$filters['direction']);
        } else {
            $query->orderBy($filters['sort'] === 'manual' ? 'board_rank' : $filters['sort'], $filters['direction']);
        }
        $tickets = $query->orderBy('id')->paginate(100)->withQueryString()->through(fn ($ticket) => TicketPageData::row($ticket));
        $selected = ! empty($filters['ticket']) ? $project->tickets()->with(['workType', 'priority', 'assignee', 'reporter', 'workflowVersion'])->findOrFail($filters['ticket']) : null;

        return Inertia::render('projects/workspace', [...TicketPageData::shared($request->user(), $project, $selected), 'view' => $view, 'tickets' => $tickets, 'filters' => $filters, 'statusCounts' => $counts, 'sortOptions' => $sortOptions, 'selectedAssignee' => $selectedAssignee]);
    }

    public static function overview(Project $project): array
    {
        return [
            'total' => $project->tickets()->count(), 'completed' => $project->tickets()->whereNotNull('completed_at')->count(),
            'statusCounts' => $project->tickets()->selectRaw('state, count(*) as total')->groupBy('state')->pluck('total', 'state'),
            'overdue' => $project->tickets()->whereNull('completed_at')->whereDate('due_date', '<', now(Project::TEAM_TIMEZONE)->format('Y-m-d'))->count(),
            'upcoming' => $project->tickets()->with(['workType', 'priority', 'assignee', 'workflowVersion'])->whereNull('completed_at')->whereNotNull('due_date')->orderBy('due_date')->orderBy('id')->limit(6)->get()->map(fn ($ticket) => TicketPageData::row($ticket)),
            'activity' => TicketEvent::whereHas('ticket', fn ($q) => $q->where('project_id', $project->id))->with(['actor:id,name', 'ticket:id,key,summary'])->latest('id')->limit(8)->get()->map(fn ($event) => ['id' => $event->id, 'actor' => $event->actor->name, 'action' => $event->action, 'ticket' => $event->ticket->only(['id', 'key', 'summary']), 'created_at' => $event->created_at->toISOString(), 'status' => $event->changes['after']['status'] ?? null]),
        ];
    }
}

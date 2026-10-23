<?php

namespace App\Http\Controllers;

use App\Models\Project;
use App\Models\Ticket;
use App\Services\TicketService;
use App\Support\TicketPageData;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;

class TicketController extends Controller
{
    public function index(Request $request, Project $project): Response
    {
        TicketPageData::authorize($request->user(), $project);
        $filters = $request->validate(['q' => ['nullable', 'string', 'max:255'], 'work_type_id' => ['nullable', 'integer', Rule::exists('work_types', 'id')->where('project_id', $project->id)], 'priority_id' => ['nullable', 'integer', Rule::exists('priorities', 'id')->where('project_id', $project->id)], 'assignee_id' => ['nullable', 'regex:/^(unassigned|[0-9]+)$/'], 'due_from' => ['nullable', 'date_format:Y-m-d'], 'due_to' => ['nullable', 'date_format:Y-m-d', 'after_or_equal:due_from'], 'sort' => ['nullable', Rule::in(['number', 'summary', 'priority', 'due_date', 'updated_at'])], 'direction' => ['nullable', Rule::in(['asc', 'desc'])], 'per_page' => ['nullable', 'integer', 'min:1', 'max:100'], 'ticket' => ['nullable', 'integer']]);
        $selected = ! empty($filters['ticket']) ? $project->tickets()->with(['workType', 'priority', 'assignee', 'reporter'])->findOrFail($filters['ticket']) : null;
        $selectedAssignee = null;
        if (! empty($filters['assignee_id']) && $filters['assignee_id'] !== 'unassigned') {
            $member = $project->members()->where('user_id', $filters['assignee_id'])->with('user:id,name')->first();
            if (! $member) {
                throw ValidationException::withMessages(['assignee_id' => 'Choose an account from this project.']);
            }
            $selectedAssignee = $member->user->only(['id', 'name']);
        }
        $sort = $filters['sort'] ?? 'updated_at';
        $direction = $filters['direction'] ?? 'desc';
        $tickets = $project->tickets()->select('tickets.*')->with(['workType', 'priority', 'assignee'])
            ->when($filters['q'] ?? null, fn ($q, $term) => $q->where(fn ($q) => $q->where('summary', 'like', '%'.$term.'%')->orWhere('key', 'like', '%'.$term.'%')))
            ->when($filters['work_type_id'] ?? null, fn ($q, $id) => $q->where('work_type_id', $id))
            ->when($filters['priority_id'] ?? null, fn ($q, $id) => $q->where('priority_id', $id))
            ->when($filters['assignee_id'] ?? null, fn ($q, $id) => $id === 'unassigned' ? $q->whereNull('assignee_id') : $q->where('assignee_id', $id))
            ->when($filters['due_from'] ?? null, fn ($q, $date) => $q->whereDate('due_date', '>=', $date))
            ->when($filters['due_to'] ?? null, fn ($q, $date) => $q->whereDate('due_date', '<=', $date));
        if ($sort === 'priority') {
            $tickets->join('priorities', 'priorities.id', '=', 'tickets.priority_id')->orderBy('priorities.position', $direction);
        } else {
            $tickets->orderBy('tickets.'.$sort, $direction);
        }
        $tickets = $tickets->orderBy('tickets.id', $direction)->paginate($filters['per_page'] ?? 20)->withQueryString()->through(fn ($ticket) => TicketPageData::row($ticket));

        return Inertia::render('tickets/index', [...TicketPageData::shared($request->user(), $project, $selected), 'selectedAssignee' => $selectedAssignee, 'tickets' => $tickets, 'filters' => [...array_fill_keys(['q', 'work_type_id', 'priority_id', 'assignee_id', 'due_from', 'due_to'], ''), ...$filters, 'sort' => $sort, 'direction' => $direction]]);
    }

    public function show(Request $request, Project $project, Ticket $ticket): Response
    {
        TicketPageData::authorize($request->user(), $project);
        abort_unless($ticket->project_id === $project->id, 404);

        return Inertia::render('tickets/show', TicketPageData::shared($request->user(), $project, $ticket));
    }

    public function store(Request $request, Project $project, TicketService $service): RedirectResponse
    {
        TicketPageData::authorize($request->user(), $project, 'create');
        $ticket = $service->create($request->user(), $project, $request->except(['_token']));

        return redirect()->route('tickets.index', ['project' => $project, 'ticket' => $ticket->id])->with('status', 'Ticket created.');
    }

    public function update(Request $request, Project $project, Ticket $ticket, TicketService $service): RedirectResponse
    {
        TicketPageData::authorize($request->user(), $project, 'update');
        abort_unless($ticket->project_id === $project->id, 404);
        $values = $request->validate(['revision' => ['required', 'integer', 'min:1']]);
        $service->update($request->user(), $ticket, $values['revision'], $request->except(['_token', 'revision']));

        return redirect()->back()->with('status', 'Ticket saved.');
    }

    public function assignees(Request $request, Project $project): JsonResponse
    {
        TicketPageData::authorize($request->user(), $project);
        $filters = $request->validate(['q' => ['nullable', 'string', 'max:255']]);
        $members = $project->members()->whereNull('removed_at')->whereHas('user', fn ($q) => $q->where('is_active', true)->when($filters['q'] ?? null, fn ($q, $term) => $q->where('name', 'like', '%'.$term.'%')))->where(fn ($q) => $q->whereHas('user', fn ($q) => $q->where('is_system_admin', true))->orWhereHas('roles.permissions', fn ($q) => $q->where('permissions.key', 'project.view')))->with('user:id,name')->orderBy('id')->limit(25)->get();

        return response()->json($members->map(fn ($member) => ['value' => $member->user_id, 'label' => $member->user->name]));
    }
}

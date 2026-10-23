<?php

namespace App\Http\Controllers;

use App\Models\Project;
use App\Models\Ticket;
use App\Services\WorkflowService;
use App\Support\ProjectPageData;
use App\Support\TicketPageData;
use App\Support\WorkflowDefaults;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class WorkflowController extends Controller
{
    public function edit(Request $request, Project $project, WorkflowService $service): Response
    {
        ProjectPageData::authorize($request->user(), $project, 'update');
        $workflow = $service->current($project);

        return Inertia::render('projects/workflow', [...ProjectPageData::shared($request->user(), $project), 'workflow' => $workflow->only(['id', 'version', 'definition']), 'stageDefaults' => WorkflowDefaults::NEW_STAGE, 'ticketCount' => $project->tickets()->count()]);
    }

    public function publish(Request $request, Project $project, WorkflowService $service): RedirectResponse
    {
        ProjectPageData::authorize($request->user(), $project, 'update');
        $data = $request->validate(['version_id' => ['required', 'integer'], 'definition' => ['required', 'array'], 'mapping' => ['present', 'array']]);
        $service->publish($request->user(), $project, $data['version_id'], $data['definition'], $data['mapping']);

        return back()->with('status', 'Workflow published. Existing tickets were mapped to the new version.');
    }

    public function transition(Request $request, Project $project, Ticket $ticket, WorkflowService $service): RedirectResponse
    {
        $this->authorizeTicket($request, $project, $ticket);
        $data = $request->validate($this->transitionRules());
        $service->transition($request->user(), $ticket, $data['revision'], $data['state'], $data['resolution'] ?? null, $data['reason'] ?? null);

        return back()->with('status', 'Ticket status changed.');
    }

    public function move(Request $request, Project $project, Ticket $ticket, WorkflowService $service): RedirectResponse
    {
        $this->authorizeTicket($request, $project, $ticket);
        $data = $request->validate([...$this->transitionRules(), 'before_id' => ['nullable', 'integer', 'min:1'], 'board_revision' => ['required', 'integer', 'min:1']]);
        $service->move($request->user(), $ticket, $data['revision'], $data['state'], $data['before_id'] ?? null, $data['board_revision'], $data['resolution'] ?? null, $data['reason'] ?? null);

        return back()->with('status', 'Board updated.');
    }

    public function schedule(Request $request, Project $project, Ticket $ticket, WorkflowService $service): RedirectResponse
    {
        $this->authorizeTicket($request, $project, $ticket);
        $data = $request->validate(['revision' => ['required', 'integer', 'min:1'], 'start_date' => ['present', 'nullable', 'string'], 'due_date' => ['present', 'nullable', 'string']]);
        $service->schedule($request->user(), $ticket, $data['revision'], $data['start_date'], $data['due_date']);

        return back()->with('status', 'Ticket dates saved.');
    }

    private function authorizeTicket(Request $request, Project $project, Ticket $ticket): void
    {
        TicketPageData::authorize($request->user(), $project, 'update');
        abort_unless($ticket->project_id === $project->id, 404);
    }

    private function transitionRules(): array
    {
        return ['revision' => ['required', 'integer', 'min:1'], 'state' => ['required', 'string', 'max:30'], 'resolution' => ['nullable', 'string', 'max:40'], 'reason' => ['nullable', 'string', 'max:2000']];
    }
}

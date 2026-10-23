<?php

namespace App\Http\Controllers;

use App\Models\Project;
use App\Models\ProjectInvitation;
use App\Services\ProjectInvitationService;
use App\Support\ProjectPageData;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;

class ProjectInvitationController extends Controller
{
    public function store(Request $request, Project $project, ProjectInvitationService $service): RedirectResponse
    {
        ProjectPageData::authorize($request->user(), $project, 'members');
        $values = $request->validate(['revision' => ['required', 'integer', 'min:1'], 'email' => ['required', 'email', 'max:254'], 'role_ids' => ['present', 'array']]);
        $service->issue($request->user(), $project, $values['revision'], $values['email'], $values['role_ids']);

        return ProjectPageData::redirect($request->user(), $project, 'members')->with('status', 'Project invitation sent.');
    }

    public function resend(Request $request, Project $project, ProjectInvitation $invitation, ProjectInvitationService $service): RedirectResponse
    {
        ProjectPageData::authorize($request->user(), $project, 'members');
        abort_unless($invitation->project_id === $project->id, 404);
        $values = $request->validate(['revision' => ['required', 'integer', 'min:1'], 'role_ids' => ['present', 'array']]);
        $service->resend($request->user(), $project, $values['revision'], $invitation, $values['role_ids']);

        return ProjectPageData::redirect($request->user(), $project, 'members')->with('status', 'Project invitation resent.');
    }

    public function revoke(Request $request, Project $project, ProjectInvitation $invitation, ProjectInvitationService $service): RedirectResponse
    {
        ProjectPageData::authorize($request->user(), $project, 'members');
        abort_unless($invitation->project_id === $project->id, 404);
        $values = $request->validate(['revision' => ['required', 'integer', 'min:1']]);
        $service->revoke($request->user(), $project, $values['revision'], $invitation);

        return ProjectPageData::redirect($request->user(), $project, 'members')->with('status', 'Project invitation revoked.');
    }

    public function show(Request $request, string $token, ProjectInvitationService $service): Response
    {
        try {
            $invitation = $service->available($request->user(), $token);

            return Inertia::render('projects/accept-invitation', ['valid' => true, 'token' => $token, 'invitation' => ['projectName' => $invitation->project->name, 'roles' => $invitation->roles->pluck('name')]]);
        } catch (ValidationException) {
            return Inertia::render('projects/accept-invitation', ['valid' => false]);
        }
    }

    public function accept(Request $request, string $token, ProjectInvitationService $service): RedirectResponse
    {
        $project = $service->accept($request->user(), $token);

        return ProjectPageData::redirect($request->user(), $project)->with('status', 'Project invitation accepted.');
    }
}

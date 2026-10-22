<?php

namespace App\Http\Controllers;

use App\Models\Project;
use App\Models\ProjectMember;
use App\Services\ProjectService;
use App\Support\ProjectPageData;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class ProjectMemberController extends Controller
{
    public function index(Request $request, Project $project): Response
    {
        ProjectPageData::authorize($request->user(), $project, 'members');
        $actor = $request->user();
        $keys = $project->permissionKeys($actor);
        $roles = $project->roles()->with('permissions')->orderBy('name')->get();
        $roleOptions = $roles->filter(fn ($role) => $actor->is_system_admin || ! array_diff($role->permissions->pluck('key')->all(), $keys))
            ->map(fn ($role) => ['value' => $role->id, 'label' => $role->name])->values();
        $members = $project->members()->whereNull('removed_at')->with(['user:id,name,email,is_active', 'roles.permissions'])->orderBy('id')->paginate(15, ['*'], 'members_page')->withQueryString()
            ->through(function ($member) use ($actor, $keys) {
                $assigned = $member->roles->flatMap(fn ($role) => $role->permissions->pluck('key'))->unique()->all();

                return ['id' => $member->id, 'name' => $member->user->name, 'email' => $member->user->email, 'is_active' => $member->user->is_active,
                    'role_ids' => $member->roles->modelKeys(), 'roleNames' => $member->roles->pluck('name'), 'canManage' => $actor->is_system_admin || ! array_diff($assigned, $keys)];
            });
        $invitations = $project->invitations()->with(['roles:id,name', 'user:id,is_active'])->latest('id')->paginate(10, ['id', 'project_id', 'user_id', 'email', 'expires_at', 'accepted_at', 'revoked_at'], 'invitations_page')->withQueryString();
        $joinedUserIds = $project->members()->whereNull('removed_at')->whereIn('user_id', $invitations->getCollection()->pluck('user_id'))->pluck('user_id')->all();
        $invitations->through(fn ($i) => ['id' => $i->id, 'email' => $i->email, 'role_ids' => $i->roles->modelKeys(), 'roleNames' => $i->roles->pluck('name'),
            'statusLabel' => $i->accepted_at ? 'Accepted' : ($i->revoked_at ? 'Revoked' : ($i->expires_at->isPast() ? 'Expired' : 'Pending')),
            'canResend' => $i->user->is_active && ! in_array($i->user_id, $joinedUserIds, true),
            'canRevoke' => ! $i->accepted_at && ! $i->revoked_at]);

        return Inertia::render('projects/members', [...ProjectPageData::shared($actor, $project), 'members' => $members, 'invitations' => $invitations, 'roleOptions' => $roleOptions]);
    }

    public function store(Request $request, Project $project, ProjectService $service): RedirectResponse
    {
        ProjectPageData::authorize($request->user(), $project, 'members');
        $values = $request->validate(['revision' => ['required', 'integer', 'min:1'], 'email' => ['required', 'email', 'max:254'], 'role_ids' => ['present', 'array']]);
        $service->addMember($request->user(), $project, $values['revision'], $values['email'], $values['role_ids']);

        return ProjectPageData::redirect($request->user(), $project, 'members')->with('status', 'Member added.');
    }

    public function update(Request $request, Project $project, ProjectMember $member, ProjectService $service): RedirectResponse
    {
        ProjectPageData::authorize($request->user(), $project, 'members');
        abort_unless($member->project_id === $project->id, 404);
        $values = $request->validate(['revision' => ['required', 'integer', 'min:1'], 'role_ids' => ['present', 'array']]);
        $service->assignRoles($request->user(), $project, $values['revision'], $member, $values['role_ids']);

        return ProjectPageData::redirect($request->user(), $project, 'members')->with('status', 'Member roles saved.');
    }

    public function destroy(Request $request, Project $project, ProjectMember $member, ProjectService $service): RedirectResponse
    {
        ProjectPageData::authorize($request->user(), $project, 'members');
        abort_unless($member->project_id === $project->id, 404);
        $values = $request->validate(['revision' => ['required', 'integer', 'min:1']]);
        $service->removeMember($request->user(), $project, $values['revision'], $member);

        return ProjectPageData::redirect($request->user(), $project, 'members')->with('status', 'Member removed.');
    }
}

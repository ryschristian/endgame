<?php

namespace App\Http\Controllers;

use App\Models\Permission;
use App\Models\Project;
use App\Models\Role;
use App\Services\ProjectService;
use App\Support\ProjectPageData;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class ProjectRoleController extends Controller
{
    public function index(Request $request, Project $project): Response
    {
        ProjectPageData::authorize($request->user(), $project, 'roles');
        $actor = $request->user();
        $keys = $project->permissionKeys($actor);
        $roles = $project->roles()->with('permissions')->withCount('members')->orderBy('name')->paginate(20)->withQueryString()
            ->through(fn ($r) => ['id' => $r->id, 'name' => $r->name, 'permission_keys' => $r->permissions->pluck('key'), 'permissionLabels' => $r->permissions->pluck('label'), 'members_count' => $r->members_count,
                'canManage' => $actor->is_system_admin || ! array_diff($r->permissions->pluck('key')->all(), $keys)]);
        $options = Permission::query()->when(! $actor->is_system_admin, fn ($q) => $q->whereIn('key', $keys))->get()->map(fn ($p) => ['value' => $p->key, 'label' => $p->label]);

        return Inertia::render('projects/roles', [...ProjectPageData::shared($actor, $project), 'roles' => $roles, 'permissionOptions' => $options, 'defaultPermissionKeys' => ['project.view']]);
    }

    public function store(Request $request, Project $project, ProjectService $service): RedirectResponse
    {
        return $this->save($request, $project, $service);
    }

    public function update(Request $request, Project $project, Role $role, ProjectService $service): RedirectResponse
    {
        abort_unless($role->project_id === $project->id, 404);

        return $this->save($request, $project, $service, $role);
    }

    private function save(Request $request, Project $project, ProjectService $service, ?Role $role = null): RedirectResponse
    {
        ProjectPageData::authorize($request->user(), $project, 'roles');
        $values = $request->validate(['revision' => ['required', 'integer', 'min:1'], 'name' => ['required', 'string', 'max:80'], 'permission_keys' => ['required', 'array']]);
        $service->saveRole($request->user(), $project, $values['revision'], $role, $values['name'], $values['permission_keys']);

        return ProjectPageData::redirect($request->user(), $project, 'roles')->with('status', 'Role saved.');
    }

    public function destroy(Request $request, Project $project, Role $role, ProjectService $service): RedirectResponse
    {
        ProjectPageData::authorize($request->user(), $project, 'roles');
        abort_unless($role->project_id === $project->id, 404);
        $values = $request->validate(['revision' => ['required', 'integer', 'min:1']]);
        $service->deleteRole($request->user(), $project, $values['revision'], $role);

        return ProjectPageData::redirect($request->user(), $project, 'roles')->with('status', 'Role deleted.');
    }
}

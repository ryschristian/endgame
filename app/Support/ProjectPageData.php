<?php

namespace App\Support;

use App\Models\Project;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Gate;

class ProjectPageData
{
    public static function authorize(User $user, Project $project, string $ability = 'view'): void
    {
        abort_unless($project->allows($user, 'project.view'), 404);
        Gate::forUser($user)->authorize($ability, $project);
    }

    public static function shared(User $user, Project $project): array
    {
        $can = [];
        foreach (['view' => 'view', 'update' => 'update', 'members' => 'members.manage', 'roles' => 'roles.manage', 'archive' => 'archive', 'restore' => 'restore'] as $name => $key) {
            $can[$name] = $project->allows($user, 'project.'.$key);
        }
        $can['tickets'] = $project->allows($user, 'project.view') && $project->allows($user, 'ticket.view');

        return ['project' => [...$project->only(['id', 'key', 'name', 'description', 'timezone', 'archived_at', 'revision']), 'statusLabel' => $project->archived_at ? 'Archived' : 'Active'], 'can' => $can];
    }

    public static function redirect(User $user, Project $project, string $section = ''): RedirectResponse
    {
        $project = $project->fresh();
        $user = $user->fresh();
        if (! $project->allows($user, 'project.view')) {
            return redirect()->route('projects.index');
        }
        $permission = ['members' => 'project.members.manage', 'roles' => 'project.roles.manage'][$section] ?? 'project.view';
        if (! $project->allows($user, $permission)) {
            $section = '';
        }

        return redirect('/projects/'.$project->id.($section ? '/'.$section : ''));
    }
}

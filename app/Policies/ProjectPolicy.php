<?php

namespace App\Policies;

use App\Models\Project;
use App\Models\User;

class ProjectPolicy
{
    public function create(User $user): bool
    {
        return $user->is_active && $user->is_system_admin;
    }

    public function view(User $user, Project $project): bool
    {
        return $project->allows($user, 'project.view');
    }

    public function update(User $user, Project $project): bool
    {
        return $project->allows($user, 'project.update');
    }

    public function members(User $user, Project $project): bool
    {
        return $project->allows($user, 'project.members.manage');
    }

    public function roles(User $user, Project $project): bool
    {
        return $project->allows($user, 'project.roles.manage');
    }

    public function archive(User $user, Project $project): bool
    {
        return $project->allows($user, 'project.archive');
    }

    public function restore(User $user, Project $project): bool
    {
        return $project->allows($user, 'project.restore');
    }
}

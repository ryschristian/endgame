<?php

namespace App\Policies;

use App\Models\Project;
use App\Models\Ticket;
use App\Models\User;

class TicketPolicy
{
    public function access(User $user, Project $project, string $permission = 'view'): bool
    {
        return $project->allows($user, 'project.view') && $project->allows($user, 'ticket.view') && $project->allows($user, 'ticket.'.$permission);
    }

    public function view(User $user, Ticket $ticket): bool
    {
        return $this->access($user, $ticket->project);
    }

    public function create(User $user, Project $project): bool
    {
        return $this->access($user, $project, 'create');
    }

    public function update(User $user, Ticket $ticket): bool
    {
        return $this->access($user, $ticket->project, 'update');
    }
}

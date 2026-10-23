<?php

namespace App\Support;

class ProjectPermissions
{
    public const LABELS = [
        'project.view' => 'View project',
        'project.update' => 'Edit project settings',
        'project.members.manage' => 'Manage members',
        'project.roles.manage' => 'Manage roles',
        'project.archive' => 'Archive project',
        'project.restore' => 'Restore project',
        'ticket.view' => 'View tickets',
        'ticket.create' => 'Create tickets',
        'ticket.update' => 'Edit and assign tickets',
        'ticket.configure' => 'Configure ticket catalogs',
    ];

    public static function keys(): array
    {
        return array_keys(self::LABELS);
    }

    public static function managerKeys(): array
    {
        return array_values(array_filter(self::keys(), fn ($key) => str_starts_with($key, 'project.')));
    }
}

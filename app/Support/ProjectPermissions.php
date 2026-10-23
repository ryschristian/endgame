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
    ];

    public static function keys(): array
    {
        return array_keys(self::LABELS);
    }
}

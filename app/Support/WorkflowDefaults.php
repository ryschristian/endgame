<?php

namespace App\Support;

use App\Models\Project;
use App\Models\WorkflowVersion;

class WorkflowDefaults
{
    public const NEW_STAGE = ['label' => 'New stage', 'color' => '#8b5cf6', 'completed' => false];

    public static function initialize(Project $project): WorkflowVersion
    {
        return WorkflowVersion::firstOrCreate(['project_id' => $project->id, 'version' => 1], ['definition' => [
            'initial' => 'open',
            'statuses' => [
                ['key' => 'open', 'label' => 'Open', 'color' => '#a8a29e', 'completed' => false],
                ['key' => 'in_progress', 'label' => 'In Progress', 'color' => '#d99a13', 'completed' => false],
                ['key' => 'resolved', 'label' => 'Resolved', 'color' => '#288b57', 'completed' => true],
            ],
            'transitions' => [
                ['from' => 'open', 'to' => 'in_progress'],
                ['from' => 'in_progress', 'to' => 'open'],
                ['from' => 'in_progress', 'to' => 'resolved'],
                ['from' => 'resolved', 'to' => 'open'],
            ],
            'resolutions' => [
                ['key' => 'fixed', 'label' => 'Completed'],
                ['key' => 'cancelled', 'label' => 'Cancelled'],
                ['key' => 'duplicate', 'label' => 'Duplicate'],
            ],
        ]]);
    }
}

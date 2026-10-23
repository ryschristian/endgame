<?php

use App\Models\Project;
use Illuminate\Support\Facades\DB;

function ticketValues(Project $project): array
{
    return [
        'summary' => 'Build the homepage',
        'work_type_id' => DB::table('work_types')->where('project_id', $project->id)->where('is_default', true)->value('id'),
        'priority_id' => DB::table('priorities')->where('project_id', $project->id)->where('is_default', true)->value('id'),
    ];
}

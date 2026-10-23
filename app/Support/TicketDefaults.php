<?php

namespace App\Support;

use App\Models\Project;
use Illuminate\Support\Facades\DB;

class TicketDefaults
{
    public static function initialize(Project $project): void
    {
        foreach (['work_types' => ['Task', 'Bug', 'Story'], 'priorities' => ['Low', 'Medium', 'High', 'Urgent']] as $table => $names) {
            foreach ($names as $position => $name) {
                DB::table($table)->insertOrIgnore(['project_id' => $project->id, 'name' => $name, 'position' => $position, 'is_active' => true, 'is_default' => $name === ($table === 'work_types' ? 'Task' : 'Medium')]);
            }
        }
        DB::table('project_counters')->insertOrIgnore(['project_id' => $project->id, 'next_number' => 1]);
    }
}

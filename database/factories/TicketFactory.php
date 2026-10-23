<?php

namespace Database\Factories;

use App\Models\Project;
use App\Models\User;
use App\Support\TicketDefaults;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Facades\DB;

class TicketFactory extends Factory
{
    public function definition(): array
    {
        return ['project_id' => Project::factory(), 'reporter_id' => User::factory(), 'summary' => fake()->sentence(5), 'state' => 'open', 'revision' => 1];
    }

    public function configure(): static
    {
        return $this->afterMaking(function ($ticket) {
            $project = Project::findOrFail($ticket->project_id);
            TicketDefaults::initialize($project);
            $ticket->work_type_id ??= DB::table('work_types')->where('project_id', $project->id)->where('is_default', true)->value('id');
            $ticket->priority_id ??= DB::table('priorities')->where('project_id', $project->id)->where('is_default', true)->value('id');
            $ticket->number ??= fake()->unique()->numberBetween(1, 1000000);
            $ticket->key ??= $project->key.'-'.str_pad((string) $ticket->number, 5, '0', STR_PAD_LEFT);
        });
    }
}

<?php

namespace Database\Factories;

use App\Models\User;
use App\Services\ProjectService;
use App\Support\TicketDefaults;
use App\Support\WorkflowDefaults;
use Illuminate\Database\Eloquent\Factories\Factory;

class ProjectFactory extends Factory
{
    public function definition(): array
    {
        return ['key' => strtoupper(fake()->unique()->lexify('??????')), 'name' => fake()->company(), 'timezone' => 'Asia/Manila'];
    }

    public function configure(): static
    {
        return $this->afterCreating(function ($project) {
            TicketDefaults::initialize($project);
            WorkflowDefaults::initialize($project);
            $manager = User::factory()->create();
            app(ProjectService::class)->initializeManager($project, $manager);
        });
    }
}

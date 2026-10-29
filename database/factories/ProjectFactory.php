<?php

namespace Database\Factories;

use App\Enums\ProjectStatus;
use App\Enums\ProjectType;
use App\Models\Client;
use App\Models\Project;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Project>
 */
class ProjectFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'name' => fake()->catchPhrase(),
            'client_id' => Client::factory(),
            'type' => ProjectType::BusinessWebsite,
            'status' => ProjectStatus::InProgress,
            'start_date' => today()->subWeeks(2),
            'due_date' => today()->addMonth(),
        ];
    }

    /**
     * Put the project in the given workflow status.
     */
    public function status(ProjectStatus $status): static
    {
        return $this->state(fn (array $attributes) => ['status' => $status]);
    }
}

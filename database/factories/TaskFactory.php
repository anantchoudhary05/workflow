<?php

namespace Database\Factories;

use App\Enums\TaskStatus;
use App\Models\Project;
use App\Models\Task;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Task>
 */
class TaskFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'project_id' => Project::factory(),
            'title' => fake()->sentence(4),
            'status' => TaskStatus::Ready,
            'due_date' => today()->addWeek(),
            'estimated_hours' => 4,
        ];
    }

    /**
     * Indicate that the task is past its due date.
     */
    public function overdue(): static
    {
        return $this->state(fn (array $attributes) => ['due_date' => today()->subDays(2)]);
    }
}

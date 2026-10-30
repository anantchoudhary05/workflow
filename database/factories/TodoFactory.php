<?php

namespace Database\Factories;

use App\Enums\Priority;
use App\Models\Todo;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Todo>
 */
class TodoFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'user_id' => User::factory(),
            'title' => fake()->sentence(4),
            'notes' => null,
            'due_date' => fake()->dateTimeBetween('now', '+2 weeks')->format('Y-m-d'),
            'priority' => Priority::Medium,
        ];
    }

    /**
     * Indicate that the to-do is done.
     */
    public function done(): static
    {
        return $this->state(fn (array $attributes) => ['completed_at' => now()]);
    }
}

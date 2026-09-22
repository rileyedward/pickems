<?php

namespace Database\Factories;

use App\Enums\WeekStatus;
use App\Models\Season;
use App\Models\Week;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Week>
 */
class WeekFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'season_id' => Season::factory(),
            'number' => fake()->unique()->numberBetween(1, 18),
            'status' => WeekStatus::Draft,
            'locks_at' => null,
        ];
    }

    public function open(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => WeekStatus::Open,
            'locks_at' => $attributes['locks_at'] ?? now()->addDays(2),
        ]);
    }

    public function locked(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => WeekStatus::Open,
            'locks_at' => now()->subHour(),
        ]);
    }

    public function closed(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => WeekStatus::Closed,
            'locks_at' => now()->subDays(5),
            'closed_at' => now(),
        ]);
    }
}

<?php

namespace Database\Factories;

use App\Models\Entry;
use App\Models\User;
use App\Models\Week;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Entry>
 */
class EntryFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'week_id' => Week::factory(),
            'user_id' => User::factory()->active(),
            'tiebreaker_guess' => null,
            'submitted_at' => null,
        ];
    }

    public function submitted(int $tiebreakerGuess = 42): static
    {
        return $this->state(fn (array $attributes) => [
            'submitted_at' => now(),
            'tiebreaker_guess' => $tiebreakerGuess,
        ]);
    }
}

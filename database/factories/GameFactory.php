<?php

namespace Database\Factories;

use App\Enums\GameStatus;
use App\Models\Game;
use App\Models\Team;
use App\Models\Week;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Game>
 */
class GameFactory extends Factory
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
            'espn_event_id' => null,
            'home_team_id' => Team::factory(),
            'away_team_id' => Team::factory(),
            'kickoff_at' => now()->addDays(3),
            'home_score' => null,
            'away_score' => null,
            'status' => GameStatus::Scheduled,
            'is_tbd_flex' => false,
            'is_tiebreaker' => false,
            'manual_override' => false,
        ];
    }

    /**
     * A finished game with the given score.
     */
    public function final(int $homeScore, int $awayScore): static
    {
        return $this->state(fn (array $attributes) => [
            'home_score' => $homeScore,
            'away_score' => $awayScore,
            'status' => GameStatus::Final,
            'kickoff_at' => now()->subDay(),
        ]);
    }

    public function tiebreaker(): static
    {
        return $this->state(fn (array $attributes) => ['is_tiebreaker' => true]);
    }
}

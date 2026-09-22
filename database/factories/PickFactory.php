<?php

namespace Database\Factories;

use App\Models\Entry;
use App\Models\Game;
use App\Models\Pick;
use App\Models\Team;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Pick>
 */
class PickFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'entry_id' => Entry::factory(),
            'game_id' => Game::factory(),
            'team_id' => Team::factory(),
        ];
    }
}

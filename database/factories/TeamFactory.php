<?php

namespace Database\Factories;

use App\Models\Team;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Team>
 */
class TeamFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'espn_id' => (string) fake()->unique()->numberBetween(1, 99999),
            'abbreviation' => strtoupper(fake()->unique()->lexify('???')),
            'location' => fake()->city(),
            'name' => ucfirst(fake()->word()),
            'display_name' => fn (array $attributes) => "{$attributes['location']} {$attributes['name']}",
            'color' => ltrim(fake()->hexColor(), '#'),
            'alternate_color' => ltrim(fake()->hexColor(), '#'),
            'logo_path' => null,
        ];
    }
}

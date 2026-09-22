<?php

use App\Models\Entry;
use App\Models\Game;
use App\Models\User;
use App\Models\Week;
use App\Support\WeekStandings;

/**
 * Give an entry picks: true = picked the home team, false = the away team.
 *
 * @param  list<bool>  $homePicks
 */
function pickHomeTeams(Entry $entry, array $homePicks): Entry
{
    $entry->week->games->values()->each(fn (Game $game, int $i) => $entry->picks()->create([
        'game_id' => $game->id,
        'team_id' => $homePicks[$i] ? $game->home_team_id : $game->away_team_id,
    ]));

    return $entry;
}

function entryFor(Week $week, string $name, int $guess): Entry
{
    return Entry::factory()
        ->for($week)
        ->for(User::factory()->active()->state(['name' => $name]))
        ->submitted($guess)
        ->create();
}

beforeEach(function () {
    // Home wins, away wins, tie-breaker (home 24 – 20 = 44 points).
    $this->week = Week::factory()->locked()->create();
    Game::factory()->for($this->week)->final(21, 17)->create(['kickoff_at' => now()->subDays(3)]);
    Game::factory()->for($this->week)->final(10, 28)->create(['kickoff_at' => now()->subDays(2)]);
    Game::factory()->for($this->week)->final(24, 20)->tiebreaker()->create(['kickoff_at' => now()->subDay()]);
});

test('entries are placed by correct picks and earn points from the table', function () {
    $alice = pickHomeTeams(entryFor($this->week, 'Alice Adams', 40), [true, false, true]);
    $bob = pickHomeTeams(entryFor($this->week, 'Bob Brown', 44), [true, true, true]);
    $cara = pickHomeTeams(entryFor($this->week, 'Cara Cole', 44), [false, true, false]);

    $standings = WeekStandings::for($this->week->fresh());
    $rows = $standings->rows();

    expect($rows->pluck('entry.id')->all())->toBe([$alice->id, $bob->id, $cara->id])
        ->and($rows->pluck('correct')->all())->toBe([3, 2, 0])
        ->and($rows->pluck('placement')->all())->toBe([1, 2, 3])
        ->and($rows->pluck('points')->all())->toBe([10.0, 7.0, 5.0])
        ->and($standings->winners()->pluck('id')->all())->toBe([$alice->id]);
});

test('a tie on correct picks goes to the closest tie-breaker guess', function () {
    pickHomeTeams(entryFor($this->week, 'Alice Adams', 30), [true, false, true]);
    $bob = pickHomeTeams(entryFor($this->week, 'Bob Brown', 50), [true, false, true]);

    $standings = WeekStandings::for($this->week->fresh());

    expect($standings->winners()->pluck('id')->all())->toBe([$bob->id])
        ->and($standings->rows()->pluck('placement')->all())->toBe([1, 2]);
});

test('two entries tied for a placement split the points for both spots', function () {
    $bob = pickHomeTeams(entryFor($this->week, 'Bob Brown', 40), [true, false, true]);
    $alice = pickHomeTeams(entryFor($this->week, 'Alice Adams', 48), [true, false, true]);
    $cara = pickHomeTeams(entryFor($this->week, 'Cara Cole', 10), [false, true, false]);

    $rows = WeekStandings::for($this->week->fresh())->rows()->keyBy(fn (array $row) => $row['entry']->id);

    expect($rows[$alice->id])->placement->toBe(1)->points->toBe(8.5)
        ->and($rows[$bob->id])->placement->toBe(1)->points->toBe(8.5)
        ->and($rows[$cara->id])->placement->toBe(3)->points->toBe(5.0);
});

test('three entries tied split three spots', function () {
    pickHomeTeams(entryFor($this->week, 'Alice Adams', 44), [true, true, true]);
    foreach (['Bob Brown', 'Cara Cole', 'Dan Dunn'] as $name) {
        pickHomeTeams(entryFor($this->week, $name, 40), [true, false, false]);
    }

    $rows = WeekStandings::for($this->week->fresh())->rows();

    expect($rows->pluck('placement')->all())->toBe([1, 2, 2, 2])
        ->and($rows->pluck('points')->all())->toBe([10.0, 5.0, 5.0, 5.0]);
});

test('points split averages and rounds to two places', function () {
    expect(WeekStandings::pointsFor(1, 3, [10, 7, 5]))->toBe(7.33)
        ->and(WeekStandings::pointsFor(2, 2, [10, 7, 5]))->toBe(6.0)
        ->and(WeekStandings::pointsFor(3, 2, [10, 7, 5]))->toBe(2.5);
});

test('a placement past the end of the table scores nothing', function () {
    $this->week->season->update(['points_table' => [10]]);
    pickHomeTeams(entryFor($this->week, 'Alice Adams', 44), [true, false, true]);
    pickHomeTeams(entryFor($this->week, 'Bob Brown', 44), [false, false, false]);

    expect(WeekStandings::for($this->week->fresh())->rows()->pluck('points')->all())->toBe([10.0, 0.0]);
});

test('entries that were never submitted are not ranked', function () {
    Entry::factory()->for($this->week)->create();
    $bob = pickHomeTeams(entryFor($this->week, 'Bob Brown', 44), [false, true, false]);

    $standings = WeekStandings::for($this->week->fresh());

    expect($standings->rows())->toHaveCount(1)
        ->and($standings->winners()->pluck('id')->all())->toBe([$bob->id]);
});

test('nobody gets a point for a tied nfl game', function () {
    $this->week->games()->first()->update(['home_score' => 17, 'away_score' => 17]);
    $alice = pickHomeTeams(entryFor($this->week, 'Alice Adams', 44), [true, false, true]);

    expect(WeekStandings::for($this->week->fresh())->correctCount($alice->fresh()))->toBe(2);
});

test('the tie-breaker only counts once its game is final', function () {
    $this->week->games()->where('is_tiebreaker', true)->update(['status' => 'in_progress']);
    $alice = pickHomeTeams(entryFor($this->week, 'Alice Adams', 44), [true, false, false]);
    $bob = pickHomeTeams(entryFor($this->week, 'Bob Brown', 10), [true, false, false]);

    $standings = WeekStandings::for($this->week->fresh());

    expect($standings->tiebreakerTotal())->toBeNull()
        ->and($standings->winners()->pluck('id')->sort()->values()->all())->toBe([$alice->id, $bob->id])
        ->and($standings->rows()->pluck('points')->all())->toBe([8.5, 8.5]);
});

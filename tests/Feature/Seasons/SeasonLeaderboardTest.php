<?php

use App\Models\Entry;
use App\Models\Season;
use App\Models\User;
use App\Models\Week;
use App\Support\SeasonLeaderboard;

beforeEach(function () {
    $this->season = Season::factory()->create();
    $this->week1 = Week::factory()->for($this->season)->closed()->create(['number' => 1]);
    $this->week2 = Week::factory()->for($this->season)->closed()->create(['number' => 2]);
    $this->week3 = Week::factory()->for($this->season)->open()->create(['number' => 3]);

    $this->alice = User::factory()->active()->create(['name' => 'Alice']);
    $this->bob = User::factory()->active()->create(['name' => 'Bob']);
    $this->cara = User::factory()->active()->create(['name' => 'Cara']);

    $result = fn (Week $week, User $user, ?int $placement, float $points, ?int $correct) => Entry::factory()
        ->for($week)->for($user)
        ->state(['submitted_at' => $placement === null ? null : now(), 'placement' => $placement, 'points' => $points, 'correct_count' => $correct])
        ->create();

    $result($this->week1, $this->alice, 1, 10, 12);
    $result($this->week1, $this->bob, 2, 7, 10);
    $result($this->week1, $this->cara, null, 0, null);

    $result($this->week2, $this->alice, 3, 5, 9);
    $result($this->week2, $this->bob, 1, 8.5, 11);
    $result($this->week2, $this->cara, 1, 8.5, 11);

    // An open week's projected results don't count.
    Entry::factory()->for($this->week3)->for($this->cara)->submitted()->create(['points' => 10, 'placement' => 1]);
});

test('totals are summed across closed weeks', function () {
    $rows = SeasonLeaderboard::for($this->season)->rows()->keyBy(fn (array $row) => $row['user']->id);

    expect($rows[$this->alice->id])
        ->total_points->toBe(15.0)->weeks_won->toBe(1)->total_correct->toBe(21)->weeks_played->toBe(2)->dnp_count->toBe(0)
        ->and($rows[$this->bob->id])
        ->total_points->toBe(15.5)->weeks_won->toBe(1)->total_correct->toBe(21)
        ->and($rows[$this->cara->id])
        ->total_points->toBe(8.5)->weeks_played->toBe(1)->dnp_count->toBe(1);
});

test('rows sort by points, then weeks won, then total correct', function () {
    $dan = User::factory()->active()->create(['name' => 'Dan']);
    Entry::factory()->for($this->week1)->for($dan)->submitted()->create(['placement' => 4, 'points' => 15, 'correct_count' => 30]);

    $rows = SeasonLeaderboard::for($this->season)->rows();

    // Alice and Dan both have 15 points; Alice has a week won.
    expect($rows->map(fn (array $row) => $row['user']->name)->all())->toBe(['Bob', 'Alice', 'Dan', 'Cara'])
        ->and($rows->pluck('rank')->all())->toBe([1, 2, 3, 4]);
});

test('players level on every key share a rank', function () {
    $dan = User::factory()->active()->create(['name' => 'Dan']);
    Entry::factory()->for($this->week1)->for($dan)->submitted()->create(['placement' => 1, 'points' => 15, 'correct_count' => 21]);

    $rows = SeasonLeaderboard::for($this->season)->rows();

    expect($rows->map(fn (array $row) => [$row['user']->name, $row['rank']])->all())
        ->toBe([['Bob', 1], ['Alice', 2], ['Dan', 2], ['Cara', 4]]);
});

test('the cumulative series has one point per closed week', function () {
    $board = SeasonLeaderboard::for($this->season);
    $rows = $board->rows()->keyBy(fn (array $row) => $row['user']->id);

    expect($board->weekNumbers())->toBe([1, 2])
        ->and($rows[$this->alice->id]['cumulative'])->toBe([10.0, 15.0])
        ->and($rows[$this->cara->id]['cumulative'])->toBe([0.0, 8.5]);
});

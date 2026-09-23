<?php

use App\Models\Entry;
use App\Models\Game;
use App\Models\User;
use App\Models\Week;
use Inertia\Testing\AssertableInertia as Assert;

beforeEach(function () {
    $this->admin = User::factory()->admin()->create();
});

test('the admin home lands on the open week', function () {
    $week = openWeekWithGames(1);

    $this->actingAs($this->admin)->get('/admin')->assertRedirect(route('admin.weeks.show', $week));
});

test('the admin week page renders', function () {
    $week = openWeekWithGames(2);
    Entry::factory()->for($week)->count(2)->create();

    $this->actingAs($this->admin)
        ->get(route('admin.weeks.show', $week))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page->component('Admin/Weeks/Show')->has('games', 2)->has('entries', 2));
});

test('an admin can add and remove an entry', function () {
    $week = openWeekWithGames(1);
    $user = User::factory()->active()->create();

    $this->actingAs($this->admin)->post(route('admin.entries.store', $week), ['user_id' => $user->id])->assertSessionHasNoErrors();
    $entry = $week->entries()->firstWhere('user_id', $user->id);
    expect($entry)->not->toBeNull();

    $this->actingAs($this->admin)->delete(route('admin.entries.destroy', $entry))->assertSessionHasNoErrors();
    expect($entry->fresh())->toBeNull();
});

test('the season points table can be edited from the admin', function () {
    $week = Week::factory()->closed()->create();

    $this->actingAs($this->admin)
        ->put(route('admin.seasons.points', $week->season), ['points_table' => [3, 2, 1]])
        ->assertSessionHasNoErrors();

    expect($week->season->fresh()->points_table)->toBe([3, 2, 1]);
});

test('editing a game by hand protects it from syncs', function () {
    $week = Week::factory()->locked()->create();
    $game = Game::factory()->for($week)->create(['kickoff_at' => now()->subHour()]);

    $this->actingAs($this->admin)->put(route('admin.games.update', $game), [
        'home_team_id' => $game->home_team_id,
        'away_team_id' => $game->away_team_id,
        'kickoff_at' => now()->toDateTimeString(),
        'home_score' => 27,
        'away_score' => 3,
        'status' => 'final',
    ])->assertSessionHasNoErrors();

    expect($game->fresh())->home_score->toBe(27)->manual_override->toBeTrue()->isFinal()->toBeTrue();
});

test('a final game needs both scores', function () {
    $game = Game::factory()->for(Week::factory()->locked())->create();

    $this->actingAs($this->admin)->put(route('admin.games.update', $game), [
        'home_team_id' => $game->home_team_id,
        'away_team_id' => $game->away_team_id,
        'kickoff_at' => now()->toDateTimeString(),
        'home_score' => 27,
        'away_score' => null,
        'status' => 'final',
    ])->assertSessionHasErrors('home_score');
});

test('an admin can lock picks early to reveal the board', function () {
    $week = openWeekWithGames(2);

    $this->actingAs($this->admin)->post(route('admin.weeks.lock', $week))->assertSessionHasNoErrors();

    expect($week->fresh())->is_locked->toBeTrue()
        ->and($week->fresh()->phase())->toBe('locked');
});

test('a manual lock survives an edit to a game', function () {
    $week = openWeekWithGames(2);
    $this->actingAs($this->admin)->post(route('admin.weeks.lock', $week));

    $game = $week->games->first();
    $this->actingAs($this->admin)->put(route('admin.games.update', $game), [
        'home_team_id' => $game->home_team_id,
        'away_team_id' => $game->away_team_id,
        'kickoff_at' => $game->kickoff_at->toIso8601String(),
        'status' => $game->status->value,
    ]);

    expect($week->fresh()->is_locked)->toBeTrue();
});

test('only a week taking picks can be locked', function () {
    $week = Week::factory()->create();

    $this->actingAs($this->admin)->post(route('admin.weeks.lock', $week))->assertSessionHasErrors('week');

    expect($week->fresh()->locks_at)->toBeNull();
});

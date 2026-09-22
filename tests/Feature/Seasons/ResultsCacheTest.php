<?php

use App\Models\Entry;
use App\Models\Game;
use App\Models\Team;
use App\Models\User;
use App\Models\Week;
use Illuminate\Support\Facades\Cache;
use Inertia\Testing\AssertableInertia as Assert;

beforeEach(function () {
    $this->viewer = User::factory()->active()->create();
    $this->week = Week::factory()->closed()->create(['number' => 1]);
    $this->season = $this->week->season;
    $this->player = User::factory()->active()->create(['name' => 'Alice']);
    $this->entry = Entry::factory()->for($this->week)->for($this->player)->submitted()
        ->create(['placement' => 1, 'points' => 10, 'correct_count' => 5]);
});

test('the leaderboard and closed week boards are cached after the first view', function () {
    expect(Cache::has("results:season:{$this->season->id}:leaderboard"))->toBeFalse()
        ->and(Cache::has("results:week:{$this->week->id}:board"))->toBeFalse();

    $this->actingAs($this->viewer)->get('/')->assertOk();

    expect(Cache::has("results:season:{$this->season->id}:leaderboard"))->toBeTrue()
        ->and(Cache::has("results:week:{$this->week->id}:board"))->toBeTrue();
});

test('open week boards are never cached', function () {
    $open = Week::factory()->for($this->season)->open()->create(['number' => 2]);

    $this->actingAs($this->viewer)->get(route('weeks.show', [$this->season->year, $open->number]))->assertOk();

    expect(Cache::has("results:week:{$open->id}:board"))->toBeFalse();
});

test('changing an entry refreshes the leaderboard', function () {
    $this->actingAs($this->viewer)->get(route('seasons.show', $this->season->year))
        ->assertInertia(fn (Assert $page) => $page->where('leaderboard.0.total_points', 10));

    $this->entry->update(['points' => 7]);

    $this->actingAs($this->viewer)->get(route('seasons.show', $this->season->year))
        ->assertInertia(fn (Assert $page) => $page->where('leaderboard.0.total_points', 7));
});

test('reopening a week drops it from the cached leaderboard', function () {
    $this->actingAs($this->viewer)->get(route('seasons.show', $this->season->year))
        ->assertInertia(fn (Assert $page) => $page->has('leaderboard', 1));

    $this->week->update(['status' => 'open', 'closed_at' => null]);

    $this->actingAs($this->viewer)->get(route('seasons.show', $this->season->year))
        ->assertInertia(fn (Assert $page) => $page->has('leaderboard', 0));
});

test('renaming a player refreshes every cached page they appear on', function () {
    $this->actingAs($this->viewer)->get('/')
        ->assertInertia(fn (Assert $page) => $page->where('topThree.0.user.name', 'Alice'));

    $this->player->update(['nickname' => 'Ace']);

    $this->actingAs($this->viewer)->get('/')
        ->assertInertia(fn (Assert $page) => $page
            ->where('topThree.0.user.name', 'Ace')
            ->where('board.participants.0.user.name', 'Ace'));
});

test('logging in does not clear the cache', function () {
    $this->actingAs($this->viewer)->get('/');

    $this->viewer->forceFill(['remember_token' => 'new-token'])->save();

    expect(Cache::has("results:season:{$this->season->id}:leaderboard"))->toBeTrue();
});

test('editing a closed week game refreshes its board', function () {
    $game = Game::factory()->for($this->week)->final(20, 10)->create();

    $this->actingAs($this->viewer)->get(route('weeks.show', [$this->season->year, 1]))
        ->assertInertia(fn (Assert $page) => $page->where('board.games.0.home_score', 20));

    $game->update(['home_score' => 24]);

    $this->actingAs($this->viewer)->get(route('weeks.show', [$this->season->year, 1]))
        ->assertInertia(fn (Assert $page) => $page->where('board.games.0.home_score', 24));
});

test('changing a team refreshes closed week boards', function () {
    $game = Game::factory()->for($this->week)->final(20, 10)->create();

    $this->actingAs($this->viewer)->get(route('weeks.show', [$this->season->year, 1]));

    Team::find($game->home_team_id)->update(['display_name' => 'Renamed Team']);

    $this->actingAs($this->viewer)->get(route('weeks.show', [$this->season->year, 1]))
        ->assertInertia(fn (Assert $page) => $page->where('board.games.0.home.display_name', 'Renamed Team'));
});

test('a player profile reads their totals from the cached leaderboard', function () {
    $this->actingAs($this->viewer)->get(route('users.show', $this->player))
        ->assertInertia(fn (Assert $page) => $page
            ->where('totals.total_points', 10)
            ->where('totals.weeks_won', 1)
            ->missing('totals.user')
            ->missing('totals.cumulative'));
});

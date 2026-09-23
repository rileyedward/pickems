<?php

use App\Models\Entry;
use App\Models\User;
use App\Models\Week;
use Inertia\Testing\AssertableInertia as Assert;

beforeEach(function () {
    $this->user = User::factory()->active()->create();
});

test('guests are sent to the login page', function () {
    $week = Week::factory()->closed()->create();

    $this->get('/')->assertRedirect(route('login'));
    $this->get(route('seasons.show', $week->season->year))->assertRedirect(route('login'));
    $this->get(route('weeks.show', [$week->season->year, $week->number]))->assertRedirect(route('login'));
    $this->get(route('users.show', $this->user))->assertRedirect(route('login'));
});

test('the home page works before any week is published', function () {
    Week::factory()->create();

    $this->actingAs($this->user)->get('/')->assertOk()->assertInertia(fn (Assert $page) => $page
        ->component('Home')
        ->where('board', null)
        ->where('myEntry', null));
});

test('picks stay hidden until the week locks', function () {
    $week = openWeekWithGames(2);
    $entry = Entry::factory()->for($week)->for($this->user)->submitted()->create();
    $entry->picks()->create(['game_id' => $week->games->first()->id, 'team_id' => $week->games->first()->home_team_id]);

    $this->actingAs($this->user)->get('/')->assertInertia(fn (Assert $page) => $page
        ->component('Home')
        ->where('board.week.id', $week->id)
        ->where('myEntry.submitted', true)
        ->has('board.participants', 1)
        ->where('board.participants.0.submitted', true)
        ->where('board.standings', null));

    $week->update(['locks_at' => now()->subMinute()]);

    $this->actingAs($this->user)->get('/')->assertInertia(fn (Assert $page) => $page
        ->has('board.standings', 1)
        ->where("board.standings.0.picks.{$week->games->first()->id}", $week->games->first()->home_team_id));
});

test('entries that were never submitted show as did-not-play at the bottom', function () {
    $week = openWeekWithGames(1, ['locks_at' => now()->subMinute()]);
    Entry::factory()->for($week)->create();
    Entry::factory()->for($week)->submitted()->create();

    $this->actingAs($this->user)
        ->get(route('weeks.show', [$week->season->year, $week->number]))
        ->assertInertia(fn (Assert $page) => $page
            ->component('Weeks/Show')
            ->has('board.standings', 2)
            ->where('board.standings.0.submitted', true)
            ->where('board.standings.0.placement', 1)
            ->where('board.standings.1.submitted', false)
            ->where('board.standings.1.placement', null));
});

test('draft weeks are not shown', function () {
    $week = Week::factory()->create();

    $this->actingAs($this->user)->get(route('weeks.show', [$week->season->year, $week->number]))->assertNotFound();
});

test('the season page shows the leaderboard, chart data and week podiums', function () {
    $week = Week::factory()->closed()->create(['number' => 1]);
    Entry::factory()->for($week)->for($this->user)->submitted()->create(['correct_count' => 12, 'placement' => 1, 'points' => 10]);
    Entry::factory()->for($week)->submitted()->create(['correct_count' => 9, 'placement' => 2, 'points' => 7]);

    $this->actingAs($this->user)
        ->get(route('seasons.show', $week->season->year))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('Seasons/Show')
            ->where('weeks.0.podium.0.id', $this->user->id)
            ->where('weeks.0.podium.0.points', 10)
            ->where('leaderboard.0.user.id', $this->user->id)
            ->where('leaderboard.0.total_points', 10)
            ->where('leaderboard.0.weeks_won', 1)
            ->where('chartWeeks', [1])
            ->has('leaderboard', 2));
});

test('a user profile shows season totals and week results', function () {
    $week = Week::factory()->closed()->create(['number' => 3]);
    Entry::factory()->for($week)->for($this->user)->submitted()->create(['correct_count' => 11, 'placement' => 2, 'points' => 7]);

    $this->actingAs(User::factory()->create())
        ->get(route('users.show', $this->user))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('Users/Show')
            ->where('user.id', $this->user->id)
            ->where('totals.total_points', 7)
            ->where('weeks.0.number', 3)
            ->where('weeks.0.placement', 2)
            ->where('weeks.0.points', 7));
});

test('the players page lists active users', function () {
    User::factory()->create(['name' => 'Waiting Wally']);
    User::factory()->admin()->create();

    $this->actingAs($this->user)
        ->get(route('users.index'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page->component('Users/Index')->has('users', 1));
});

test('admins have no public profile', function () {
    $admin = User::factory()->admin()->create();

    $this->actingAs($this->user)->get(route('users.show', $admin))->assertNotFound();
});

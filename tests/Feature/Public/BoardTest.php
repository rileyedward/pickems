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

test('submitted picks are visible before the lock, standings only after', function () {
    $week = openWeekWithGames(2);
    $game = $week->games->first();
    $entry = Entry::factory()->for($week)->for($this->user)->submitted()->create(['tiebreaker_guess' => 41]);
    $entry->picks()->create(['game_id' => $game->id, 'team_id' => $game->home_team_id]);

    $this->actingAs($this->user)->get('/')->assertInertia(fn (Assert $page) => $page
        ->component('Home')
        ->where('board.week.id', $week->id)
        ->where('myEntry.submitted', true)
        ->where('board.can_view_picks', true)
        ->has('board.participants', 1)
        ->where('board.participants.0.submitted', true)
        ->where('board.participants.0.tiebreaker_guess', 41)
        ->where("board.participants.0.picks.{$game->id}", $game->home_team_id)
        ->where('board.standings', null));

    $week->update(['locks_at' => now()->subMinute()]);

    $this->actingAs($this->user)->get('/')->assertInertia(fn (Assert $page) => $page
        ->has('board.standings', 1)
        ->where("board.standings.0.picks.{$week->games->first()->id}", $week->games->first()->home_team_id));
});

test('unsubmitted picks stay hidden from other players', function () {
    $week = openWeekWithGames(1);
    $game = $week->games->first();
    Entry::factory()->for($week)->for($this->user)->submitted()->create();
    $entry = Entry::factory()->for($week)->create(['tiebreaker_guess' => 30]);
    $entry->picks()->create(['game_id' => $game->id, 'team_id' => $game->home_team_id]);

    $this->actingAs($this->user)->get('/')->assertInertia(function (Assert $page) use ($entry) {
        $page->where('board.can_view_picks', true);

        expect(collect($page->toArray()['props']['board']['participants'])->firstWhere('user.id', $entry->user_id))
            ->submitted->toBeFalse()
            ->tiebreaker_guess->toBeNull()
            ->picks->toBe([]);
    });
});

test('before the lock, picks are hidden until the viewer has submitted their own', function () {
    $week = openWeekWithGames(1);
    $game = $week->games->first();
    $mine = Entry::factory()->for($week)->for($this->user)->create();
    $theirs = Entry::factory()->for($week)->submitted()->create(['tiebreaker_guess' => 30]);
    $theirs->picks()->create(['game_id' => $game->id, 'team_id' => $game->home_team_id]);

    $their = fn (Assert $page) => collect($page->toArray()['props']['board']['participants'])
        ->firstWhere('user.id', $theirs->user_id);

    $this->actingAs($this->user)->get('/')->assertInertia(function (Assert $page) use ($their) {
        $page->where('board.can_view_picks', false);

        expect($their($page))
            ->submitted->toBeTrue()
            ->tiebreaker_guess->toBeNull()
            ->picks->toBe([]);
    });

    $mine->update(['submitted_at' => now()]);

    $this->actingAs($this->user)->get('/')->assertInertia(function (Assert $page) use ($their, $game) {
        $page->where('board.can_view_picks', true);

        expect($their($page))
            ->tiebreaker_guess->toBe(30)
            ->picks->toBe([$game->id => $game->home_team_id]);
    });
});

test('players who are not entered cannot see picks until the lock', function () {
    $week = openWeekWithGames(1);
    Entry::factory()->for($week)->submitted()->create(['tiebreaker_guess' => 30]);

    $this->actingAs($this->user)->get('/')->assertInertia(fn (Assert $page) => $page
        ->where('board.can_view_picks', false)
        ->where('board.participants.0.tiebreaker_guess', null));

    $week->update(['locks_at' => now()->subMinute()]);

    $this->actingAs($this->user)->get('/')->assertInertia(fn (Assert $page) => $page
        ->where('board.can_view_picks', true)
        ->where('board.participants.0.tiebreaker_guess', 30));
});

test('admins can see submitted picks before the lock', function () {
    $week = openWeekWithGames(1);
    Entry::factory()->for($week)->submitted()->create(['tiebreaker_guess' => 30]);

    $this->actingAs(User::factory()->admin()->create())->get('/')->assertInertia(fn (Assert $page) => $page
        ->where('board.can_view_picks', true)
        ->where('board.participants.0.tiebreaker_guess', 30));
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

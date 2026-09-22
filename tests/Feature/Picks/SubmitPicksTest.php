<?php

use App\Models\Entry;
use App\Models\Game;
use App\Models\User;
use Inertia\Testing\AssertableInertia as Assert;

beforeEach(function () {
    $this->week = openWeekWithGames(2);
    $this->user = User::factory()->active()->create();
    $this->entry = Entry::factory()->for($this->week)->for($this->user)->create();
    $this->games = $this->week->games;
});

/**
 * @return array<int, int> game id => home team id
 */
function homePicks($games): array
{
    return $games->mapWithKeys(fn (Game $game) => [$game->id => $game->home_team_id])->all();
}

test('an active user sees their picks page for the open week', function () {
    $this->actingAs($this->user)
        ->get(route('picks.show'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('Picks/Show')
            ->has('games', 2)
            ->where('week.is_locked', false));
});

test('an active user can submit and then change their picks before the lock', function () {
    $this->actingAs($this->user)
        ->put(route('picks.update'), ['picks' => homePicks($this->games), 'tiebreaker_guess' => 41])
        ->assertRedirect(route('picks.show'));

    expect($this->entry->fresh())->submitted_at->not->toBeNull()->tiebreaker_guess->toBe(41)
        ->and($this->entry->picks()->count())->toBe(2);

    $awayPicks = $this->games->mapWithKeys(fn (Game $game) => [$game->id => $game->away_team_id])->all();
    $this->actingAs($this->user)->put(route('picks.update'), ['picks' => $awayPicks, 'tiebreaker_guess' => 38]);

    expect($this->entry->picks()->pluck('team_id')->sort()->values()->all())
        ->toBe($this->games->pluck('away_team_id')->sort()->values()->all())
        ->and($this->entry->fresh()->tiebreaker_guess)->toBe(38);
});

test('an inactive user is sent home', function () {
    $inactive = User::factory()->create();

    $this->actingAs($inactive)->get(route('picks.show'))->assertRedirect(route('home'));
    $this->actingAs($inactive)
        ->put(route('picks.update'), ['picks' => homePicks($this->games), 'tiebreaker_guess' => 40])
        ->assertRedirect(route('home'));
});

test('guests are sent to the login page', function () {
    $this->get(route('picks.show'))->assertRedirect(route('login'));
});

test('a user only ever touches their own entry', function () {
    $other = Entry::factory()->for($this->week)->create();

    $this->actingAs($this->user)
        ->put(route('picks.update'), ['picks' => homePicks($this->games), 'tiebreaker_guess' => 40]);

    expect($this->entry->fresh()->submitted_at)->not->toBeNull()
        ->and($other->fresh()->submitted_at)->toBeNull()
        ->and($other->picks()->count())->toBe(0);
});

test('an active user without an entry this week is sent home', function () {
    $late = User::factory()->active()->create();

    $this->actingAs($late)->get(route('picks.show'))->assertRedirect(route('home'));
});

test('every game needs a pick for one of its two teams', function () {
    $picks = homePicks($this->games);
    $picks[$this->games->first()->id] = $this->games->last()->home_team_id;
    unset($picks[$this->games->last()->id]);

    $this->actingAs($this->user)
        ->put(route('picks.update'), ['picks' => $picks, 'tiebreaker_guess' => 40])
        ->assertSessionHasErrors(["picks.{$this->games->first()->id}", "picks.{$this->games->last()->id}"]);

    expect($this->entry->fresh()->submitted_at)->toBeNull();
});

test('the tie-breaker guess is required', function () {
    $this->actingAs($this->user)
        ->put(route('picks.update'), ['picks' => homePicks($this->games)])
        ->assertSessionHasErrors('tiebreaker_guess');
});

test('a locked week rejects picks', function () {
    $this->week->update(['locks_at' => now()->subMinute()]);

    $this->actingAs($this->user)
        ->put(route('picks.update'), ['picks' => homePicks($this->games), 'tiebreaker_guess' => 40])
        ->assertSessionHasErrors('picks');

    expect($this->entry->fresh()->submitted_at)->toBeNull();

    $this->actingAs($this->user)
        ->get(route('picks.show'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page->where('week.is_locked', true));
});

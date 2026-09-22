<?php

use App\Models\Entry;
use App\Models\Game;
use App\Models\User;

beforeEach(function () {
    $this->admin = User::factory()->admin()->create();
    $this->week = openWeekWithGames(2);
    $this->entry = Entry::factory()->for($this->week)->create();
    $this->picks = $this->week->games->mapWithKeys(fn (Game $game) => [$game->id => $game->home_team_id])->all();
});

test('an admin can enter picks for a player before the lock', function () {
    $this->actingAs($this->admin)
        ->put(route('admin.entries.picks', $this->entry), ['picks' => $this->picks, 'tiebreaker_guess' => 45])
        ->assertSessionHasNoErrors();

    expect($this->entry->fresh())->submitted_at->not->toBeNull()->tiebreaker_guess->toBe(45)
        ->and($this->entry->picks()->count())->toBe(2);
});

test('an admin cannot enter picks after the lock', function () {
    $this->week->update(['locks_at' => now()->subMinute()]);

    $this->actingAs($this->admin)
        ->put(route('admin.entries.picks', $this->entry), ['picks' => $this->picks, 'tiebreaker_guess' => 45])
        ->assertSessionHasErrors('picks');

    expect($this->entry->fresh()->submitted_at)->toBeNull();
});

test('a non-admin cannot enter picks for someone else', function () {
    $this->actingAs(User::factory()->active()->create())
        ->put(route('admin.entries.picks', $this->entry), ['picks' => $this->picks, 'tiebreaker_guess' => 45])
        ->assertForbidden();
});

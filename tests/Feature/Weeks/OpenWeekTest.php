<?php

use App\Actions\Weeks\OpenWeek;
use App\Enums\WeekStatus;
use App\Models\Game;
use App\Models\User;
use App\Models\Week;
use Illuminate\Validation\ValidationException;

test('opening a week enters every active user and locks at the first kickoff', function () {
    $week = Week::factory()->create();
    $first = Game::factory()->for($week)->create(['kickoff_at' => now()->addDays(2)]);
    $last = Game::factory()->for($week)->create(['kickoff_at' => now()->addDays(6)]);
    $active = User::factory()->active()->count(2)->create();
    User::factory()->create();
    User::factory()->admin()->create();

    app(OpenWeek::class)->handle($week);

    $week->refresh();
    expect($week->status)->toBe(WeekStatus::Open)
        ->and($week->locks_at->equalTo($first->kickoff_at))->toBeTrue()
        ->and($week->entries()->pluck('user_id')->sort()->values()->all())->toBe($active->pluck('id')->sort()->values()->all())
        ->and($last->fresh()->is_tiebreaker)->toBeTrue()
        ->and($first->fresh()->is_tiebreaker)->toBeFalse();
});

test('an admin-chosen tie-breaker game is kept', function () {
    $week = Week::factory()->create();
    $chosen = Game::factory()->for($week)->tiebreaker()->create(['kickoff_at' => now()->addDays(2)]);
    Game::factory()->for($week)->create(['kickoff_at' => now()->addDays(6)]);

    app(OpenWeek::class)->handle($week);

    expect($week->games()->where('is_tiebreaker', true)->pluck('id')->all())->toBe([$chosen->id]);
});

test('a week cannot open while kickoff times are still to be decided', function () {
    $week = Week::factory()->create();
    Game::factory()->for($week)->create(['is_tbd_flex' => true]);

    app(OpenWeek::class)->handle($week);
})->throws(ValidationException::class, 'TBD');

test('a week with no games cannot open', function () {
    app(OpenWeek::class)->handle(Week::factory()->create());
})->throws(ValidationException::class, 'no games');

<?php

use App\Actions\Seasons\SetUpSeason;
use App\Actions\Weeks\EnrollInOpenWeek;
use App\Enums\WeekStatus;
use App\Models\Season;
use App\Models\Team;
use App\Models\User;
use App\Models\Week;
use Illuminate\Support\Facades\Storage;

beforeEach(function () {
    Storage::fake('images');
    fakeEspn([1 => 'scoreboard-2026-week-1', 2 => 'scoreboard-2026-week-2', 18 => 'scoreboard-2026-week-18']);
});

function weekNumber(int $number): Week
{
    return Season::firstWhere('year', 2026)->weeks()->where('number', $number)->firstOrFail();
}

test('finished weeks are closed and the next week opens for picks', function () {
    // After week 1, before week 2's first kickoff.
    $this->travelTo('2026-09-16 12:00:00');

    $result = app(SetUpSeason::class)->handle(2026);

    expect($result)->closed->toBe([1])->opened->toBe(2)->in_progress->toBe([])
        ->and(weekNumber(1))->status->toBe(WeekStatus::Closed)
        ->and(weekNumber(1)->tiebreakerGame)->not->toBeNull()
        ->and(weekNumber(2))->status->toBe(WeekStatus::Open)->is_locked->toBeFalse()
        ->and(weekNumber(3)->status)->toBe(WeekStatus::Draft);
});

test('it loads every team with a logo and creates no users', function () {
    $this->travelTo('2026-09-16 12:00:00');

    app(SetUpSeason::class)->handle(2026);

    expect(Team::count())->toBe(32)
        ->and(User::count())->toBe(0);
    Storage::disk('images')->assertExists('teams/sea.png');
});

test('a week that has kicked off but is not finished is left open and locked', function () {
    // Week 2's Monday night game hasn't finished in the fixture.
    $this->travelTo('2026-09-21 12:00:00');

    $result = app(SetUpSeason::class)->handle(2026);

    expect($result)->closed->toBe([1])->in_progress->toBe([2])
        ->and(weekNumber(2))->status->toBe(WeekStatus::Open)->is_locked->toBeTrue()
        ->and(weekNumber(2)->tiebreakerGame)->not->toBeNull();
});

test('an upcoming week with unset flex kickoffs stays a draft', function () {
    $this->travelTo('2026-09-21 12:00:00');

    $result = app(SetUpSeason::class)->handle(2026);

    // Weeks 3–17 have no games in the fixtures, so week 18 is next, and its
    // kickoff times are still TBD.
    expect($result['opened'])->toBeNull()
        ->and($result['notes'][0])->toContain('Week 18')
        ->and(weekNumber(18)->status)->toBe(WeekStatus::Draft);
});

test('running it again changes nothing an admin has done', function () {
    $this->travelTo('2026-09-16 12:00:00');
    app(SetUpSeason::class)->handle(2026);
    weekNumber(2)->games()->first()->update(['is_tiebreaker' => false]);
    $tiebreakers = weekNumber(2)->games()->where('is_tiebreaker', true)->pluck('id')->all();

    $result = app(SetUpSeason::class)->handle(2026);

    expect($result)->closed->toBe([])->opened->toBeNull()
        ->and(weekNumber(2)->games()->where('is_tiebreaker', true)->pluck('id')->all())->toBe($tiebreakers)
        ->and(Week::where('status', WeekStatus::Open)->count())->toBe(1);
});

test('the setup command reports what it did', function () {
    $this->travelTo('2026-09-16 12:00:00');

    $this->artisan('app:setup-season', ['year' => 2026])
        ->expectsOutputToContain('Open for picks')
        ->assertSuccessful();

    expect(weekNumber(2)->status)->toBe(WeekStatus::Open);
});

test('activating a user enters them into the week taking picks', function () {
    $this->travelTo('2026-09-16 12:00:00');
    app(SetUpSeason::class)->handle(2026);
    $admin = User::factory()->admin()->create();
    $friend = User::factory()->create();

    $this->actingAs($admin)->post(route('admin.users.update', $friend), ['is_active' => true]);

    expect(weekNumber(2)->entries()->where('user_id', $friend->id)->exists())->toBeTrue()
        ->and(weekNumber(1)->entries()->count())->toBe(0);
});

test('nobody is enrolled once picks have locked', function () {
    $week = openWeekWithGames(1, ['locks_at' => now()->subMinute()]);

    expect(app(EnrollInOpenWeek::class)->handle(User::factory()->active()->create()))->toBeNull()
        ->and($week->entries()->count())->toBe(0);
});

test('the create admin command can run without a prompt and enters the admin', function () {
    $week = openWeekWithGames(1);

    $this->artisan('app:create-admin', ['email' => 'riley@example.com', 'name' => 'Riley', '--password' => 'secret-password'])
        ->assertSuccessful();

    $admin = User::firstWhere('email', 'riley@example.com');
    expect($admin)->is_admin->toBeTrue()->is_active->toBeTrue()
        ->and($week->entries()->where('user_id', $admin->id)->exists())->toBeTrue();
});

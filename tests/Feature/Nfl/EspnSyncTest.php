<?php

use App\Actions\Nfl\SyncSeasonSchedule;
use App\Actions\Nfl\SyncTeams;
use App\Actions\Nfl\SyncWeekGames;
use App\Enums\GameStatus;
use App\Models\Game;
use App\Models\Season;
use App\Models\Team;
use App\Models\Week;
use Illuminate\Support\Facades\Storage;

beforeEach(function () {
    Storage::fake('public');
});

test('teams sync from espn with a local logo copy', function () {
    fakeEspn();

    expect(app(SyncTeams::class)->handle())->toBe(32);

    $seahawks = Team::firstWhere('abbreviation', 'SEA');

    expect($seahawks)
        ->display_name->toBe('Seattle Seahawks')
        ->color->toBe('002a5c')
        ->logo_path->toBe('teams/sea.png');
    Storage::disk('public')->assertExists('teams/sea.png');

    app(SyncTeams::class)->handle();
    expect(Team::count())->toBe(32);
});

test('a week sync creates its games and final scores', function () {
    fakeEspn([1 => 'scoreboard-2026-week-1']);
    $week = Week::factory()->for(Season::factory()->state(['year' => 2026]))->create(['number' => 1]);

    expect(app(SyncWeekGames::class)->handle($week))->toBe(16);

    $opener = Game::firstWhere('espn_event_id', '401872656');

    expect($opener)
        ->week_id->toBe($week->id)
        ->status->toBe(GameStatus::Final)
        ->home_score->toBe(13)
        ->away_score->toBe(10)
        ->kickoff_at->toIso8601String()->toBe('2026-09-10T00:20:00+00:00')
        ->and($opener->homeTeam->abbreviation)->toBe('SEA')
        ->and($opener->awayTeam->abbreviation)->toBe('NE')
        ->and($opener->winning_team_id)->toBe($opener->home_team_id);

    expect(app(SyncWeekGames::class)->handle($week))->toBe(0);
    expect(Game::count())->toBe(16);
});

test('scores are ignored for games that have not kicked off', function () {
    fakeEspn([18 => 'scoreboard-2026-week-18']);
    $week = Week::factory()->for(Season::factory()->state(['year' => 2026]))->create(['number' => 18]);

    app(SyncWeekGames::class)->handle($week);

    expect(Game::whereNotNull('home_score')->count())->toBe(0)
        ->and(Game::where('is_tbd_flex', true)->count())->toBe(16);
});

test('manually edited games are not overwritten by a sync', function () {
    fakeEspn([1 => 'scoreboard-2026-week-1']);
    $week = Week::factory()->for(Season::factory()->state(['year' => 2026]))->create(['number' => 1]);
    app(SyncWeekGames::class)->handle($week);

    $game = Game::firstWhere('espn_event_id', '401872656');
    $game->update(['home_score' => 99, 'manual_override' => true]);

    app(SyncWeekGames::class)->handle($week);

    expect($game->fresh()->home_score)->toBe(99);
});

test('an open week gets no new games but its lock follows the first kickoff', function () {
    fakeEspn([18 => 'scoreboard-2026-week-18']);
    $week = Week::factory()->open()->for(Season::factory()->state(['year' => 2026]))->create(['number' => 18]);
    $existing = Game::factory()->for($week)->create(['espn_event_id' => espnFixture('scoreboard-2026-week-18')['events'][0]['id']]);

    app(SyncWeekGames::class)->handle($week);

    expect($week->games()->count())->toBe(1)
        ->and($week->fresh()->locks_at->equalTo($existing->fresh()->kickoff_at))->toBeTrue();
});

test('a season sync creates all eighteen weeks', function () {
    fakeEspn([1 => 'scoreboard-2026-week-1', 2 => 'scoreboard-2026-week-2']);

    $season = app(SyncSeasonSchedule::class)->handle(2026);

    expect($season->weeks()->count())->toBe(18)
        ->and(Game::count())->toBe(32);
});

<?php

use App\Actions\Weeks\CloseWeek;
use App\Actions\Weeks\ReopenWeek;
use App\Enums\WeekStatus;
use App\Models\Entry;
use App\Models\Game;
use App\Models\Week;
use Illuminate\Validation\ValidationException;

beforeEach(function () {
    $this->week = Week::factory()->locked()->create();
    $this->game = Game::factory()->for($this->week)->final(24, 20)->tiebreaker()->create();

    $this->winner = Entry::factory()->for($this->week)->submitted(44)->create();
    $this->winner->picks()->create(['game_id' => $this->game->id, 'team_id' => $this->game->home_team_id]);

    $this->runnerUp = Entry::factory()->for($this->week)->submitted(44)->create();
    $this->runnerUp->picks()->create(['game_id' => $this->game->id, 'team_id' => $this->game->away_team_id]);

    $this->noShow = Entry::factory()->for($this->week)->create();
});

test('closing a week stores correct counts, placements and points', function () {
    app(CloseWeek::class)->handle($this->week);

    expect($this->week->fresh()->status)->toBe(WeekStatus::Closed)
        ->and($this->winner->fresh())->correct_count->toBe(1)->placement->toBe(1)->points->toBe('10.00')
        ->and($this->runnerUp->fresh())->correct_count->toBe(0)->placement->toBe(2)->points->toBe('7.00');
});

test('an entry that was never submitted scores zero with no placement', function () {
    app(CloseWeek::class)->handle($this->week);

    expect($this->noShow->fresh())->correct_count->toBeNull()->placement->toBeNull()->points->toBe('0.00');
});

test('a week cannot close until every game is final', function () {
    Game::factory()->for($this->week)->create(['kickoff_at' => now()->subHour(), 'status' => 'in_progress']);

    app(CloseWeek::class)->handle($this->week);
})->throws(ValidationException::class, 'final score');

test('a week cannot close while picks are still open', function () {
    $this->week->update(['locks_at' => now()->addDay()]);

    app(CloseWeek::class)->handle($this->week);
})->throws(ValidationException::class, 'still open');

test('reopening clears the stored results', function () {
    app(CloseWeek::class)->handle($this->week);
    app(ReopenWeek::class)->handle($this->week->fresh());

    expect($this->week->fresh()->status)->toBe(WeekStatus::Open)
        ->and($this->winner->fresh())->correct_count->toBeNull()->placement->toBeNull()->points->toBeNull()
        ->and($this->noShow->fresh()->points)->toBeNull();
});

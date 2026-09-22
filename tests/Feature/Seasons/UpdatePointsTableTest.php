<?php

use App\Actions\Seasons\UpdatePointsTable;
use App\Models\Entry;
use App\Models\Season;
use App\Models\Week;
use Illuminate\Validation\ValidationException;

test('the points table is saved', function () {
    $season = Season::factory()->create();

    app(UpdatePointsTable::class)->handle($season, [5, 3, 1]);

    expect($season->fresh()->points_table)->toBe([5, 3, 1]);
});

test('each place must be worth no more than the one above it', function () {
    app(UpdatePointsTable::class)->handle(Season::factory()->create(), [5, 7, 1]);
})->throws(ValidationException::class, 'no more than the place above');

test('points cannot be negative', function () {
    app(UpdatePointsTable::class)->handle(Season::factory()->create(), [5, -1]);
})->throws(ValidationException::class);

test('the table needs between 1 and 20 places', function (array $table) {
    expect(fn () => app(UpdatePointsTable::class)->handle(Season::factory()->create(), $table))
        ->toThrow(ValidationException::class);
})->with([
    'empty' => [[]],
    'too long' => [array_fill(0, 21, 1)],
]);

test('closed weeks are re-scored from their stored placements', function () {
    $season = Season::factory()->create();
    $closed = Week::factory()->for($season)->closed()->create(['number' => 1]);
    $open = Week::factory()->for($season)->open()->create(['number' => 2]);

    $first = Entry::factory()->for($closed)->submitted()->create(['placement' => 1, 'points' => 8.5, 'correct_count' => 3]);
    $tied = Entry::factory()->for($closed)->submitted()->create(['placement' => 1, 'points' => 8.5, 'correct_count' => 3]);
    $third = Entry::factory()->for($closed)->submitted()->create(['placement' => 3, 'points' => 5, 'correct_count' => 1]);
    $noShow = Entry::factory()->for($closed)->create(['points' => 0]);
    $openEntry = Entry::factory()->for($open)->submitted()->create();

    app(UpdatePointsTable::class)->handle($season, [20, 10, 4]);

    expect($first->fresh())->points->toBe('15.00')->placement->toBe(1)
        ->and($tied->fresh()->points)->toBe('15.00')
        ->and($third->fresh()->points)->toBe('4.00')
        ->and($noShow->fresh()->points)->toBe('0.00')
        ->and($openEntry->fresh()->points)->toBeNull();
});

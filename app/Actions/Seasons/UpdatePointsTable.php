<?php

namespace App\Actions\Seasons;

use App\Models\Entry;
use App\Models\Season;
use App\Models\Week;
use App\Support\WeekStandings;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;

/**
 * Change what each placement is worth, then re-score every closed week in
 * the season from the placements already stored. Nobody is re-ranked.
 */
class UpdatePointsTable
{
    /**
     * @param  array<int, mixed>  $table  index 0 = 1st place
     */
    public function handle(Season $season, array $table): Season
    {
        $table = array_values($table);

        Validator::make(['points_table' => $table], [
            'points_table' => ['required', 'array', 'min:1', 'max:20'],
            'points_table.*' => ['required', 'integer', 'min:0', 'max:1000'],
        ], [
            'points_table.max' => 'The points table can have at most 20 places.',
        ])->after(function ($validator) use ($table) {
            foreach ($table as $index => $points) {
                if ($index > 0 && is_numeric($points) && is_numeric($table[$index - 1]) && $points > $table[$index - 1]) {
                    $validator->errors()->add('points_table', 'Each place must be worth no more than the place above it.');

                    return;
                }
            }
        })->validate();

        $table = array_map('intval', $table);

        DB::transaction(function () use ($season, $table) {
            $season->update(['points_table' => $table]);

            $season->weeks()->where('status', 'closed')->with('entries')->get()
                ->each(fn (Week $week) => $this->rescore($week, $table));
        });

        return $season;
    }

    /**
     * @param  list<int>  $table
     */
    private function rescore(Week $week, array $table): void
    {
        $tiedCounts = $week->entries->whereNotNull('placement')->countBy('placement');

        $week->entries->each(fn (Entry $entry) => $entry->update([
            'points' => $entry->placement === null
                ? 0
                : WeekStandings::pointsFor($entry->placement, $tiedCounts[$entry->placement], $table),
        ]));
    }
}

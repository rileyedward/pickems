<?php

namespace App\Actions\Weeks;

use App\Enums\WeekStatus;
use App\Models\Entry;
use App\Models\Week;
use App\Support\WeekStandings;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Finalise a week: store each entry's correct count, placement and points,
 * and publish it as a result. Entries that were never submitted score 0.
 */
class CloseWeek
{
    public function handle(Week $week): Week
    {
        $standings = WeekStandings::for($week);

        $error = match (true) {
            ! $week->isOpen() => 'Only an open week can be closed.',
            ! $week->is_locked => 'Picks are still open for this week.',
            ! $standings->allGamesFinal() => 'Every game needs a final score before the week can be closed.',
            $standings->tiebreakerGame() === null => 'Choose a tie-breaker game before closing the week.',
            default => null,
        };

        if ($error !== null) {
            throw ValidationException::withMessages(['week' => $error]);
        }

        $rows = $standings->rows()->keyBy(fn (array $row) => $row['entry']->id);

        DB::transaction(function () use ($week, $rows) {
            $week->entries->each(function (Entry $entry) use ($rows) {
                $row = $rows->get($entry->id);

                $entry->update([
                    'correct_count' => $row['correct'] ?? null,
                    'placement' => $row['placement'] ?? null,
                    'points' => $row['points'] ?? 0,
                ]);
            });

            $week->update(['status' => WeekStatus::Closed, 'closed_at' => now()]);
        });

        return $week;
    }
}

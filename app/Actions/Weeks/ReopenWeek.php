<?php

namespace App\Actions\Weeks;

use App\Enums\WeekStatus;
use App\Models\Week;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Undo a close so scores or picks can be corrected.
 */
class ReopenWeek
{
    public function handle(Week $week): Week
    {
        if (! $week->isClosed()) {
            throw ValidationException::withMessages(['week' => 'Only a closed week can be reopened.']);
        }

        DB::transaction(function () use ($week) {
            $week->entries()->update(['correct_count' => null, 'placement' => null, 'points' => null]);
            $week->update(['status' => WeekStatus::Open, 'closed_at' => null]);
        });

        return $week;
    }
}

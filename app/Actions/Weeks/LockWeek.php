<?php

namespace App\Actions\Weeks;

use App\Models\Week;
use Illuminate\Validation\ValidationException;

/**
 * Lock picks now instead of waiting for the first kickoff, which reveals
 * everyone's picks on the board. Syncs and game edits leave a locked week's
 * lock alone, so it stays locked.
 */
class LockWeek
{
    public function handle(Week $week): Week
    {
        if (! $week->isOpen() || $week->is_locked) {
            throw ValidationException::withMessages(['week' => 'Only a week that is taking picks can be locked.']);
        }

        $week->update(['locks_at' => now()]);

        return $week;
    }
}

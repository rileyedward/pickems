<?php

namespace App\Actions\Weeks;

use App\Models\Entry;
use App\Models\User;
use App\Models\Week;

/**
 * Enter a newly activated user into the week that's currently taking picks,
 * so someone activated after a week opens can still play it. Does nothing
 * once picks have locked.
 */
class EnrollInOpenWeek
{
    public function handle(User $user): ?Entry
    {
        $week = Week::currentOpen();

        if (! $user->is_active || $week === null || $week->is_locked) {
            return null;
        }

        return $week->entries()->firstOrCreate(['user_id' => $user->id]);
    }
}

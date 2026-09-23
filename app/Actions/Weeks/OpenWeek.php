<?php

namespace App\Actions\Weeks;

use App\Enums\WeekStatus;
use App\Models\Game;
use App\Models\User;
use App\Models\Week;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Confirm a week's slate and enter every player. Picks lock at the
 * first kickoff, and the last kickoff becomes the tie-breaker unless an admin
 * already chose one.
 */
class OpenWeek
{
    public function handle(Week $week): Week
    {
        $games = $week->games()->get();

        $error = match (true) {
            ! $week->isDraft() => 'Only a draft week can be opened.',
            $games->isEmpty() => 'This week has no games yet. Sync the schedule or add games first.',
            $games->contains(fn (Game $game) => $game->is_tbd_flex) => 'Some kickoff times are still TBD (flex scheduling). Re-sync once the NFL sets them, or edit those games by hand.',
            $games->min('kickoff_at') <= now() => 'This week\'s first game has already kicked off.',
            default => null,
        };

        if ($error !== null) {
            throw ValidationException::withMessages(['week' => $error]);
        }

        DB::transaction(function () use ($week, $games) {
            if (! $games->contains(fn (Game $game) => $game->is_tiebreaker)) {
                $games->sortBy([['kickoff_at', 'asc'], ['id', 'asc']])->last()->update(['is_tiebreaker' => true]);
            }

            User::players()->each(fn (User $user) => $week->entries()->firstOrCreate(['user_id' => $user->id]));

            $week->update([
                'status' => WeekStatus::Open,
                'locks_at' => $games->min('kickoff_at'),
            ]);
        });

        return $week;
    }
}

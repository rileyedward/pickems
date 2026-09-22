<?php

namespace App\Actions\Picks;

use App\Models\Entry;
use App\Models\Game;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Save a user's picks for their week. Resubmitting before the lock simply
 * replaces the previous picks.
 */
class SubmitPicks
{
    /**
     * @param  array<int|string, int|string>  $picks  game id => picked team id, one for every game
     */
    public function handle(Entry $entry, array $picks, int $tiebreakerGuess): Entry
    {
        $week = $entry->week;

        if (! $week->isOpen() || $week->is_locked) {
            throw ValidationException::withMessages(['picks' => 'Picks are locked for this week.']);
        }

        $games = $week->games()->get()->keyBy('id');
        $errors = [];

        foreach ($games as $game) {
            $teamId = isset($picks[$game->id]) ? (int) $picks[$game->id] : null;

            if ($teamId === null || ! $game->involvesTeam($teamId)) {
                $errors["picks.{$game->id}"] = 'Pick a winner for this game.';
            }
        }

        if (array_diff(array_map('intval', array_keys($picks)), $games->keys()->all()) !== []) {
            $errors['picks'] = 'Those picks don\'t match this week\'s games. Refresh the page and try again.';
        }

        if ($errors !== []) {
            throw ValidationException::withMessages($errors);
        }

        DB::transaction(function () use ($entry, $games, $picks, $tiebreakerGuess) {
            $games->each(fn (Game $game) => $entry->picks()->updateOrCreate(
                ['game_id' => $game->id],
                ['team_id' => (int) $picks[$game->id]],
            ));

            $entry->update(['tiebreaker_guess' => $tiebreakerGuess, 'submitted_at' => now()]);
        });

        return $entry;
    }
}

<?php

namespace App\Actions\Weeks;

use App\Models\Game;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class SetTiebreakerGame
{
    public function handle(Game $game): void
    {
        $week = $game->week;

        if ($week->is_locked) {
            throw ValidationException::withMessages(['week' => 'The tie-breaker game can\'t change once picks are locked.']);
        }

        DB::transaction(function () use ($game, $week) {
            $week->games()->where('is_tiebreaker', true)->update(['is_tiebreaker' => false]);
            $game->update(['is_tiebreaker' => true]);
        });
    }
}

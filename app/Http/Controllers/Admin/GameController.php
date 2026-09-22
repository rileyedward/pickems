<?php

namespace App\Http\Controllers\Admin;

use App\Actions\Weeks\SetTiebreakerGame;
use App\Enums\GameStatus;
use App\Http\Controllers\Controller;
use App\Models\Game;
use App\Models\Week;
use Carbon\CarbonImmutable;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

/**
 * Hand edits to a week's games, for when ESPN is wrong or unavailable.
 * Any edit marks the game as a manual override so syncs leave it alone.
 */
class GameController extends Controller
{
    public function store(Request $request, Week $week): RedirectResponse
    {
        if (! $week->isDraft()) {
            throw ValidationException::withMessages(['week' => 'Games can only be added while the week is a draft.']);
        }

        $week->games()->create($this->validated($request) + ['manual_override' => true]);

        return back();
    }

    public function update(Request $request, Game $game): RedirectResponse
    {
        $data = $this->validated($request);

        if ($game->week->is_locked) {
            // Once picks are locked only results may change; the matchup is fixed.
            $data = collect($data)->only(['home_score', 'away_score', 'status'])->all();
        }

        $game->update($data + ['manual_override' => true]);

        if ($game->week->isOpen() && ! $game->week->is_locked) {
            $game->week->refreshLocksAt();
        }

        return back();
    }

    public function destroy(Game $game): RedirectResponse
    {
        if (! $game->week->isDraft()) {
            throw ValidationException::withMessages(['week' => 'Games can only be removed while the week is a draft.']);
        }

        $game->delete();

        return back();
    }

    public function tiebreaker(Game $game, SetTiebreakerGame $setTiebreaker): RedirectResponse
    {
        $setTiebreaker->handle($game);

        return back();
    }

    /**
     * Kickoff arrives as a local (display timezone) datetime from the form.
     *
     * @return array<string, mixed>
     */
    private function validated(Request $request): array
    {
        $data = $request->validate([
            'home_team_id' => ['required', 'integer', 'exists:teams,id', 'different:away_team_id'],
            'away_team_id' => ['required', 'integer', 'exists:teams,id'],
            'kickoff_at' => ['required', 'date'],
            'home_score' => ['nullable', 'integer', 'min:0', 'max:200'],
            'away_score' => ['nullable', 'integer', 'min:0', 'max:200'],
            'status' => ['required', Rule::enum(GameStatus::class)],
        ]);

        if ($data['status'] === GameStatus::Final->value && ($data['home_score'] === null || $data['away_score'] === null)) {
            throw ValidationException::withMessages(['home_score' => 'A final game needs both scores.']);
        }

        $data['kickoff_at'] = CarbonImmutable::parse($data['kickoff_at'], config('app.display_timezone'))->utc();
        $data['is_tbd_flex'] = false;

        return $data;
    }
}

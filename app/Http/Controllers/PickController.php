<?php

namespace App\Http\Controllers;

use App\Actions\Picks\SubmitPicks;
use App\Models\Entry;
use App\Models\Game;
use App\Models\Week;
use App\Support\WeekBoard;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

/**
 * The logged-in user's picks for the current open week.
 */
class PickController extends Controller
{
    public function show(Request $request): Response|RedirectResponse
    {
        $entry = $this->entry($request);

        if ($entry instanceof RedirectResponse) {
            return $entry;
        }

        $week = $entry->week;
        $week->load(['season', 'games.homeTeam', 'games.awayTeam']);
        $entry->load('picks');

        return Inertia::render('Picks/Show', [
            'week' => WeekBoard::week($week),
            'games' => $week->games->map(fn (Game $game) => WeekBoard::game($game))->values(),
            'entry' => [
                'submitted_at' => $entry->submitted_at?->toIso8601String(),
                'tiebreaker_guess' => $entry->tiebreaker_guess,
                'picks' => WeekBoard::picks($entry),
            ],
        ]);
    }

    public function update(Request $request, SubmitPicks $submitPicks): RedirectResponse
    {
        $entry = $this->entry($request);

        if ($entry instanceof RedirectResponse) {
            return $entry;
        }

        $validated = self::validatePicks($request);

        $submitPicks->handle($entry, $validated['picks'], (int) $validated['tiebreaker_guess']);

        Inertia::flash('toast', ['type' => 'success', 'message' => 'Picks saved. Good luck!']);

        return to_route('picks.show');
    }

    /**
     * @return array{picks: array<int|string, int|string>, tiebreaker_guess: int|string}
     */
    public static function validatePicks(Request $request): array
    {
        /** @var array{picks: array<int|string, int|string>, tiebreaker_guess: int|string} */
        return $request->validate([
            'picks' => ['required', 'array'],
            'picks.*' => ['required', 'integer'],
            'tiebreaker_guess' => ['required', 'integer', 'min:0', 'max:200'],
        ], [
            'tiebreaker_guess.required' => 'Guess the total points for the tie-breaker game.',
        ]);
    }

    /**
     * The viewer's entry in the open week, or a redirect home explaining why
     * there isn't one.
     */
    private function entry(Request $request): Entry|RedirectResponse
    {
        $week = Week::currentOpen();
        $entry = $week?->entries()->where('user_id', $request->user()->id)->first();

        if ($entry === null) {
            Inertia::flash('toast', [
                'type' => 'error',
                'message' => $week === null
                    ? 'No week is open for picks right now.'
                    : "You're not entered in week {$week->number}. Ask an admin to add you.",
            ]);

            return to_route('home');
        }

        return $entry;
    }
}

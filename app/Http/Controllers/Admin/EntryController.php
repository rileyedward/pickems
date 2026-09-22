<?php

namespace App\Http\Controllers\Admin;

use App\Actions\Picks\SubmitPicks;
use App\Http\Controllers\Controller;
use App\Http\Controllers\PickController;
use App\Models\Entry;
use App\Models\Week;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;

class EntryController extends Controller
{
    /**
     * Add a user to a week (e.g. someone activated after it opened).
     */
    public function store(Request $request, Week $week): RedirectResponse
    {
        $validated = $request->validate([
            'user_id' => ['required', 'integer', 'exists:users,id', Rule::unique('entries')->where('week_id', $week->id)],
        ]);

        if ($week->isClosed()) {
            throw ValidationException::withMessages(['week' => 'This week is closed.']);
        }

        $week->entries()->create(['user_id' => $validated['user_id']]);

        return back();
    }

    public function destroy(Entry $entry): RedirectResponse
    {
        if ($entry->week->isClosed()) {
            throw ValidationException::withMessages(['week' => 'Reopen the week to remove an entry.']);
        }

        $entry->delete();

        return back();
    }

    /**
     * Enter or edit picks on someone's behalf (for friends who text them in).
     * Same rules as the player's own form, including the lock.
     */
    public function picks(Request $request, Entry $entry, SubmitPicks $submitPicks): RedirectResponse
    {
        $validated = PickController::validatePicks($request);

        $submitPicks->handle($entry, $validated['picks'], (int) $validated['tiebreaker_guess']);

        Inertia::flash('toast', ['type' => 'success', 'message' => "Picks saved for {$entry->user->display_name}."]);

        return back();
    }
}

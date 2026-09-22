<?php

namespace App\Http\Controllers;

use App\Models\Week;
use App\Support\SeasonLeaderboard;
use App\Support\WeekBoard;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class HomeController extends Controller
{
    /**
     * The current week's board: the open week if there is one, otherwise the
     * most recently closed week. Adds the viewer's own entry status and the
     * season's top three.
     */
    public function __invoke(Request $request): Response
    {
        $week = Week::current();
        $entry = $week?->entries()->where('user_id', $request->user()->id)->first();

        return Inertia::render('Home', [
            'board' => $week ? WeekBoard::present($week) : null,
            'myEntry' => $entry ? ['submitted' => $entry->isSubmitted()] : null,
            'topThree' => $week ? array_slice(SeasonLeaderboard::cached($week->season)['rows'], 0, 3) : [],
        ]);
    }
}

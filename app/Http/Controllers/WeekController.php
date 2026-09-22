<?php

namespace App\Http\Controllers;

use App\Models\Season;
use App\Models\Week;
use App\Support\WeekBoard;
use Inertia\Inertia;
use Inertia\Response;

class WeekController extends Controller
{
    public function show(Season $season, Week $week): Response
    {
        abort_if($week->isDraft(), 404);

        return Inertia::render('Weeks/Show', [
            'board' => WeekBoard::present($week),
            'weekNumbers' => $season->weeks()->published()->pluck('number'),
        ]);
    }
}

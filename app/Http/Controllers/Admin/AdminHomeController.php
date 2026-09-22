<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Season;
use App\Models\Week;
use Illuminate\Http\RedirectResponse;

class AdminHomeController extends Controller
{
    /**
     * Land on the week that needs attention: the open week, else the next
     * draft week in the latest season, else the seasons list.
     */
    public function __invoke(): RedirectResponse
    {
        $season = Season::orderByDesc('year')->first();

        $week = $season?->weeks()->where('status', '!=', 'closed')->reorder()->orderByRaw("case when status = 'open' then 0 else 1 end")->orderBy('number')->first()
            ?? $season?->weeks()->reorder()->orderByDesc('number')->first();

        return $week instanceof Week
            ? redirect()->route('admin.weeks.show', $week)
            : redirect()->route('admin.seasons.index');
    }
}

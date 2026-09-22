<?php

namespace App\Http\Controllers\Admin;

use App\Actions\Nfl\SyncSeasonSchedule;
use App\Actions\Nfl\SyncTeams;
use App\Actions\Seasons\UpdatePointsTable;
use App\Http\Controllers\Controller;
use App\Models\Season;
use App\Models\Team;
use App\Models\Week;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class SeasonController extends Controller
{
    public function index(): Response
    {
        return Inertia::render('Admin/Seasons/Index', [
            'seasons' => Season::withCount('weeks')->orderByDesc('year')->get(['id', 'year']),
            'teamCount' => Team::count(),
            'suggestedYear' => now()->month >= 3 ? now()->year : now()->year - 1,
        ]);
    }

    /**
     * Create a season (or re-sync an existing one) from ESPN's schedule.
     */
    public function store(Request $request, SyncSeasonSchedule $syncSeason): RedirectResponse
    {
        $validated = $request->validate(['year' => ['required', 'integer', 'min:2000', 'max:2100']]);

        $season = $syncSeason->handle((int) $validated['year']);

        Inertia::flash('toast', ['type' => 'success', 'message' => "{$season->year} schedule synced from ESPN."]);

        return redirect()->route('admin.seasons.show', $season);
    }

    public function show(Season $season): Response
    {
        return Inertia::render('Admin/Seasons/Show', [
            'season' => $season->only(['id', 'year', 'points_table']),
            'closedWeeksCount' => $season->weeks()->where('status', 'closed')->count(),
            'weeks' => $season->weeks()
                ->withCount(['games', 'entries', 'entries as submitted_count' => fn ($query) => $query->whereNotNull('submitted_at')])
                ->withMin('games', 'kickoff_at')
                ->get()
                ->map(fn (Week $week) => [
                    'id' => $week->id,
                    'number' => $week->number,
                    'phase' => $week->phase(),
                    'games_count' => $week->getAttribute('games_count'),
                    'entries_count' => $week->getAttribute('entries_count'),
                    'submitted_count' => $week->getAttribute('submitted_count'),
                    'starts_at' => $week->getAttribute('games_min_kickoff_at'),
                ]),
        ]);
    }

    /**
     * Save the points table and re-score the season's closed weeks.
     */
    public function update(Request $request, Season $season, UpdatePointsTable $updatePointsTable): RedirectResponse
    {
        $updatePointsTable->handle($season, (array) $request->input('points_table', []));

        Inertia::flash('toast', ['type' => 'success', 'message' => 'Points table saved. Closed weeks were re-scored.']);

        return back();
    }

    public function syncTeams(SyncTeams $syncTeams): RedirectResponse
    {
        $count = $syncTeams->handle();

        Inertia::flash('toast', ['type' => 'success', 'message' => "{$count} teams synced from ESPN."]);

        return back();
    }
}

<?php

namespace App\Http\Controllers;

use App\Models\Entry;
use App\Models\Season;
use App\Models\Week;
use App\Support\SeasonLeaderboard;
use Inertia\Inertia;
use Inertia\Response;

class SeasonController extends Controller
{
    /**
     * The season leaderboard, the cumulative points chart, and week-by-week
     * results.
     */
    public function show(Season $season): Response
    {
        $leaderboard = SeasonLeaderboard::cached($season);

        $weeks = $season->weeks()
            ->published()
            ->with(['entries' => fn ($query) => $query->whereNotNull('submitted_at'), 'entries.user'])
            ->get();

        return Inertia::render('Seasons/Show', [
            'season' => ['id' => $season->id, 'year' => $season->year, 'points_table' => $season->points_table],
            'seasons' => Season::orderByDesc('year')->pluck('year'),
            'weeks' => $weeks->map(fn (Week $week) => [
                'number' => $week->number,
                'phase' => $week->phase(),
                'entry_count' => $week->entries->count(),
                'podium' => $week->isClosed()
                    ? $week->entries
                        ->whereNotNull('placement')
                        ->where('placement', '<=', 3)
                        ->sortBy(fn (Entry $entry) => [$entry->placement, $entry->user->display_name])
                        ->map(fn (Entry $entry) => $entry->user->toAvatar() + [
                            'placement' => $entry->placement,
                            'points' => (float) $entry->points,
                            'correct' => $entry->correct_count,
                        ])
                        ->values()
                    : [],
            ]),
            'leaderboard' => $leaderboard['rows'],
            'chartWeeks' => $leaderboard['weeks'],
        ]);
    }
}

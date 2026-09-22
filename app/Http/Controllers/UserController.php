<?php

namespace App\Http\Controllers;

use App\Models\Entry;
use App\Models\Season;
use App\Models\User;
use App\Support\SeasonLeaderboard;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class UserController extends Controller
{
    /**
     * Everyone who's playing, plus anyone who has played before.
     */
    public function index(): Response
    {
        return Inertia::render('Users/Index', [
            'users' => User::alphabetical()
                ->where(fn ($query) => $query->where('is_active', true)->orWhereHas('entries'))
                ->withCount(['entries as weeks_played' => fn ($query) => $query->whereNotNull('submitted_at')])
                ->get()
                ->map(fn (User $user) => $user->toAvatar() + [
                    'full_name' => $user->name,
                    'is_active' => $user->is_active,
                    'weeks_played' => (int) $user->getAttribute('weeks_played'),
                ]),
        ]);
    }

    /**
     * A player's profile: season totals and their week-by-week results.
     */
    public function show(Request $request, User $user): Response
    {
        $season = $request->filled('season')
            ? Season::where('year', $request->integer('season'))->firstOrFail()
            : Season::orderByDesc('year')->first();

        $totals = $season
            ? SeasonLeaderboard::for($season)->rows()->first(fn (array $row) => $row['user']->is($user))
            : null;

        $entries = $season
            ? $user->entries()
                ->whereHas('week', fn ($query) => $query->where('season_id', $season->id)->published())
                ->with('week')
                ->get()
                ->sortByDesc(fn (Entry $entry) => $entry->week->number)
            : collect();

        return Inertia::render('Users/Show', [
            'user' => $user->toAvatar() + [
                'full_name' => $user->name,
                'nickname' => $user->nickname,
                'is_active' => $user->is_active,
            ],
            'season' => $season?->year,
            'seasons' => Season::orderByDesc('year')->pluck('year'),
            'totals' => $totals ? collect($totals)->except(['user', 'cumulative'])->all() : null,
            'weeks' => $entries->map(fn (Entry $entry) => [
                'number' => $entry->week->number,
                'phase' => $entry->week->phase(),
                'submitted' => $entry->isSubmitted(),
                'placement' => $entry->placement,
                'points' => $entry->points === null ? null : (float) $entry->points,
                'correct' => $entry->correct_count,
            ])->values(),
        ]);
    }
}

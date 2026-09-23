<?php

namespace App\Http\Controllers\Admin;

use App\Actions\Nfl\SyncWeekGames;
use App\Actions\Weeks\CloseWeek;
use App\Actions\Weeks\LockWeek;
use App\Actions\Weeks\OpenWeek;
use App\Actions\Weeks\ReopenWeek;
use App\Http\Controllers\Controller;
use App\Models\Entry;
use App\Models\Team;
use App\Models\User;
use App\Models\Week;
use App\Support\WeekBoard;
use App\Support\WeekStandings;
use Illuminate\Http\RedirectResponse;
use Inertia\Inertia;
use Inertia\Response;

/**
 * The admin's weekly work screen and each step of the week's lifecycle.
 */
class WeekController extends Controller
{
    public function show(Week $week): Response
    {
        $week->load(['season', 'games.homeTeam', 'games.awayTeam', 'entries.user', 'entries.picks']);
        $standings = WeekStandings::for($week);
        $projected = $week->isClosed() ? collect() : $standings->rows()->keyBy(fn (array $row) => $row['entry']->id);

        return Inertia::render('Admin/Weeks/Show', [
            'week' => WeekBoard::week($week) + ['season_id' => $week->season_id],
            'games' => $week->games->map(fn ($game) => WeekBoard::game($game) + ['manual_override' => $game->manual_override])->values(),
            'entries' => $week->entries
                ->sortBy(fn (Entry $entry) => $entry->user->display_name)
                ->map(fn (Entry $entry) => [
                    'id' => $entry->id,
                    'user' => $entry->user->toAvatar() + ['email' => $entry->user->email],
                    'submitted_at' => $entry->submitted_at?->toIso8601String(),
                    'tiebreaker_guess' => $entry->tiebreaker_guess,
                    'correct' => $entry->isSubmitted() ? $standings->correctCount($entry) : null,
                    'placement' => $week->isClosed() ? $entry->placement : $projected->get($entry->id)['placement'] ?? null,
                    'points' => $week->isClosed()
                        ? ($entry->points === null ? null : (float) $entry->points)
                        : $projected->get($entry->id)['points'] ?? null,
                    'picks' => WeekBoard::picks($entry),
                ])
                ->values(),
            'summary' => [
                'all_games_final' => $standings->allGamesFinal(),
                'tiebreaker_total' => $standings->tiebreakerTotal(),
            ],
            'availableUsers' => User::alphabetical()
                ->where('is_admin', false)
                ->whereNotIn('id', $week->entries->pluck('user_id'))
                ->get()
                ->map(fn (User $user) => ['id' => $user->id, 'name' => $user->display_name, 'is_active' => $user->is_active]),
            'teams' => Team::orderBy('display_name')->get()->map(fn (Team $team) => $team->toSummary()),
            'adjacentWeeks' => [
                'previous' => $week->season->weeks()->where('number', $week->number - 1)->value('id'),
                'next' => $week->season->weeks()->where('number', $week->number + 1)->value('id'),
            ],
        ]);
    }

    public function sync(Week $week, SyncWeekGames $syncWeekGames): RedirectResponse
    {
        $changed = $syncWeekGames->handle($week);

        return $this->done($changed === 1 ? '1 game updated from ESPN.' : "{$changed} games updated from ESPN.");
    }

    public function open(Week $week, OpenWeek $openWeek): RedirectResponse
    {
        $openWeek->handle($week);

        return $this->done("Week {$week->number} is open with {$week->entries()->count()} players entered.");
    }

    public function lock(Week $week, LockWeek $lockWeek): RedirectResponse
    {
        $lockWeek->handle($week);

        return $this->done("Week {$week->number} is locked. Everyone's picks are on the board.");
    }

    public function close(Week $week, CloseWeek $closeWeek): RedirectResponse
    {
        $closeWeek->handle($week);

        return $this->done("Week {$week->number} closed. Points are in.");
    }

    public function reopen(Week $week, ReopenWeek $reopenWeek): RedirectResponse
    {
        $reopenWeek->handle($week);

        return $this->done("Week {$week->number} reopened.");
    }

    private function done(string $message): RedirectResponse
    {
        Inertia::flash('toast', ['type' => 'success', 'message' => $message]);

        return back();
    }
}

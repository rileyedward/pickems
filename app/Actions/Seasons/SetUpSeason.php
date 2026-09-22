<?php

namespace App\Actions\Seasons;

use App\Actions\Nfl\SyncSeasonSchedule;
use App\Actions\Nfl\SyncTeams;
use App\Actions\Weeks\CloseWeek;
use App\Actions\Weeks\OpenWeek;
use App\Enums\WeekStatus;
use App\Models\Game;
use App\Models\Season;
use App\Models\Week;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Get a season ready to play from nothing: every team and logo, the full
 * schedule with real results from ESPN, and each week put in the state it
 * would be in if the app had been running all along.
 *
 * - A week whose games are all final is closed (with nobody entered).
 * - A week that has kicked off but isn't finished is opened and locked, so
 *   the admin can close it once the results are in.
 * - The first week that hasn't kicked off yet is opened for picks.
 * - Later weeks stay drafts.
 *
 * Safe to run again: only draft weeks are touched, so anything an admin has
 * already opened, closed or edited is left alone, and a week is never opened
 * for picks while another one still is.
 */
class SetUpSeason
{
    public function __construct(
        private SyncTeams $syncTeams,
        private SyncSeasonSchedule $syncSeasonSchedule,
        private OpenWeek $openWeek,
        private CloseWeek $closeWeek,
    ) {}

    /**
     * The season year NFL football is in on a given date: January and
     * February belong to the previous year's season.
     */
    public static function currentYear(): int
    {
        return now()->month >= 3 ? now()->year : now()->year - 1;
    }

    /**
     * @return array{season: Season, closed: list<int>, in_progress: list<int>, opened: int|null, notes: list<string>}
     */
    public function handle(int $year): array
    {
        $this->syncTeams->handle();
        $season = $this->syncSeasonSchedule->handle($year);

        $result = ['season' => $season, 'closed' => [], 'in_progress' => [], 'opened' => null, 'notes' => []];
        $hasPicksOpen = $season->weeks()->where('status', WeekStatus::Open)->where('locks_at', '>', now())->exists();

        foreach ($season->weeks()->with('games')->get() as $week) {
            $games = $week->games;

            if (! $week->isDraft() || $games->isEmpty()) {
                continue;
            }

            if ($games->every(fn (Game $game) => $game->isFinal())) {
                $this->startWeek($week, $games);
                $this->closeWeek->handle($week->fresh());
                $result['closed'][] = $week->number;

                continue;
            }

            if ($games->min('kickoff_at') <= now()) {
                $this->startWeek($week, $games);
                $result['in_progress'][] = $week->number;

                continue;
            }

            if ($hasPicksOpen) {
                break;
            }

            try {
                $this->openWeek->handle($week);
                $result['opened'] = $week->number;
            } catch (ValidationException $exception) {
                $result['notes'][] = "Week {$week->number} was left as a draft: ".collect($exception->errors())->flatten()->first();
            }

            break;
        }

        return $result;
    }

    /**
     * Mark a week that is already under way as open and locked at its first
     * kickoff, with the last kickoff as the tie-breaker.
     *
     * @param  Collection<int, Game>  $games
     */
    private function startWeek(Week $week, Collection $games): void
    {
        DB::transaction(function () use ($week, $games) {
            if (! $games->contains(fn (Game $game) => $game->is_tiebreaker)) {
                $games->sortBy([['kickoff_at', 'asc'], ['id', 'asc']])->last()?->update(['is_tiebreaker' => true]);
            }

            $week->update(['status' => WeekStatus::Open, 'locks_at' => $games->min('kickoff_at')]);
        });
    }
}

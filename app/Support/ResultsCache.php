<?php

namespace App\Support;

use App\Models\Entry;
use App\Models\Game;
use App\Models\Season;
use App\Models\Team;
use App\Models\User;
use App\Models\Week;
use Closure;
use Illuminate\Support\Facades\Cache;

/**
 * Caches the read-heavy results views in the app's cache store (the
 * database by default, so no extra service is needed): each season's
 * leaderboard and each closed week's board.
 *
 * Entries are kept until something they're built from changes. Model events
 * forget the affected keys, so a cached page is never stale.
 */
class ResultsCache
{
    /**
     * @template T
     *
     * @param  Closure(): T  $build
     * @return T
     */
    public static function leaderboard(int $seasonId, Closure $build): mixed
    {
        return Cache::rememberForever(self::leaderboardKey($seasonId), $build);
    }

    /**
     * @template T
     *
     * @param  Closure(): T  $build
     * @return T
     */
    public static function weekBoard(int $weekId, Closure $build): mixed
    {
        return Cache::rememberForever(self::weekBoardKey($weekId), $build);
    }

    public static function forgetSeason(int $seasonId): void
    {
        Cache::forget(self::leaderboardKey($seasonId));
    }

    public static function forgetWeek(int $weekId): void
    {
        Cache::forget(self::weekBoardKey($weekId));
    }

    /**
     * Forget a week's board and its season's leaderboard.
     */
    public static function forgetWeekAndSeason(int $weekId): void
    {
        self::forgetWeek($weekId);

        if ($seasonId = Week::whereKey($weekId)->value('season_id')) {
            self::forgetSeason($seasonId);
        }
    }

    /**
     * Forget everything, for changes that show up on every page (a player's
     * name or photo, a team's logo).
     */
    public static function flush(): void
    {
        Season::pluck('id')->each(fn (int $id) => self::forgetSeason($id));
        Week::where('status', 'closed')->pluck('id')->each(fn (int $id) => self::forgetWeek($id));
    }

    /**
     * Wire up the model events that invalidate cached results.
     */
    public static function register(): void
    {
        foreach (['saved', 'deleted'] as $event) {
            Season::$event(fn (Season $season) => self::forgetSeason($season->id));

            Week::$event(function (Week $week) {
                self::forgetWeek($week->id);
                self::forgetSeason($week->season_id);
            });

            Game::$event(fn (Game $game) => self::forgetWeek($game->week_id));
            // Picks only change through SubmitPicks, which also saves the entry.
            Entry::$event(fn (Entry $entry) => self::forgetWeekAndSeason($entry->week_id));

            // New users and teams aren't on any cached page yet, and logins
            // touch users without changing how they're shown.
            User::$event(function (User $user) {
                if (! $user->exists || $user->wasChanged(['name', 'nickname', 'photo_path'])) {
                    self::flush();
                }
            });

            Team::$event(function (Team $team) {
                if (! $team->exists || $team->wasChanged()) {
                    self::flush();
                }
            });
        }
    }

    private static function leaderboardKey(int $seasonId): string
    {
        return "results:season:{$seasonId}:leaderboard";
    }

    private static function weekBoardKey(int $weekId): string
    {
        return "results:week:{$weekId}:board";
    }
}

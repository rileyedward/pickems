<?php

namespace App\Actions\Nfl;

use App\Models\Season;

/**
 * Create a season and all of its regular-season weeks, then pull every
 * week's games from ESPN (18 requests).
 */
class SyncSeasonSchedule
{
    public function __construct(private SyncWeekGames $syncWeekGames) {}

    public function handle(int $year): Season
    {
        $season = Season::firstOrCreate(['year' => $year]);

        foreach (range(1, Season::REGULAR_SEASON_WEEKS) as $number) {
            $week = $season->weeks()->firstOrCreate(['number' => $number]);

            $this->syncWeekGames->handle($week);
        }

        return $season;
    }
}

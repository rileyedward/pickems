<?php

namespace App\Actions\Nfl;

use App\Models\Game;
use App\Models\Week;
use App\Support\EspnClient;
use Illuminate\Support\Facades\DB;

/**
 * Pull one week's games from ESPN: kickoffs, flex status, scores and final
 * results. Used both to confirm the schedule and to fetch results.
 *
 * - Games an admin has edited by hand (`manual_override`) are left alone.
 * - New games are only added while the week is still a draft; once players
 *   are picking, the slate is fixed.
 * - An open week that hasn't locked yet has its lock moved to follow the
 *   earliest kickoff.
 */
class SyncWeekGames
{
    public function __construct(
        private EspnClient $espn,
        private UpsertTeam $upsertTeam,
    ) {}

    /**
     * @return int The number of games created or updated.
     */
    public function handle(Week $week): int
    {
        $week->loadMissing('season');

        $games = $this->espn->regularSeasonWeek($week->season->year, $week->number);

        $changed = DB::transaction(function () use ($week, $games): int {
            $changed = 0;

            foreach ($games as $data) {
                $game = Game::firstWhere('espn_event_id', $data['espn_event_id']);

                if ($game?->manual_override) {
                    continue;
                }

                if ($game === null && ! $week->isDraft()) {
                    continue;
                }

                $game ??= new Game(['espn_event_id' => $data['espn_event_id']]);

                $game->fill([
                    'week_id' => $week->id,
                    'home_team_id' => $this->upsertTeam->handle($data['home'])->id,
                    'away_team_id' => $this->upsertTeam->handle($data['away'])->id,
                    'kickoff_at' => $data['kickoff_at'],
                    'home_score' => $data['home_score'],
                    'away_score' => $data['away_score'],
                    'status' => $data['status'],
                    'is_tbd_flex' => $data['is_tbd_flex'],
                ]);

                if ($game->isDirty()) {
                    $game->save();
                    $changed++;
                }
            }

            return $changed;
        });

        if ($week->isOpen() && ! $week->is_locked) {
            $week->refreshLocksAt();
        }

        return $changed;
    }
}

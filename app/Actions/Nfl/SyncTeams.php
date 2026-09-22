<?php

namespace App\Actions\Nfl;

use App\Support\EspnClient;

class SyncTeams
{
    public function __construct(
        private EspnClient $espn,
        private UpsertTeam $upsertTeam,
    ) {}

    /**
     * @return int The number of teams synced.
     */
    public function handle(): int
    {
        $teams = $this->espn->teams();

        foreach ($teams as $team) {
            $this->upsertTeam->handle($team);
        }

        return count($teams);
    }
}

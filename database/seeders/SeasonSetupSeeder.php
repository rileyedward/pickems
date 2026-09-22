<?php

namespace Database\Seeders;

use App\Actions\Seasons\SetUpSeason;
use Illuminate\Database\Seeder;

/**
 * The real current season from ESPN, with finished weeks closed and the next
 * week open for picks. Creates no users. Also run by the
 * `set_up_current_season` migration so a fresh production database starts
 * out ready to play.
 */
class SeasonSetupSeeder extends Seeder
{
    public function run(SetUpSeason $setUpSeason): void
    {
        $setUpSeason->handle(SetUpSeason::currentYear());
    }
}

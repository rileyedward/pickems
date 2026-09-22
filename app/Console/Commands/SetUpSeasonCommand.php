<?php

namespace App\Console\Commands;

use App\Actions\Seasons\SetUpSeason;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;

#[Signature('app:setup-season {year? : Defaults to the current NFL season}')]
#[Description('Load teams, logos and the schedule from ESPN, close finished weeks and open the next one for picks')]
class SetUpSeasonCommand extends Command
{
    /**
     * Execute the console command.
     */
    public function handle(SetUpSeason $setUpSeason): int
    {
        $year = $this->argument('year') !== null ? (int) $this->argument('year') : SetUpSeason::currentYear();

        $this->components->info("Setting up the {$year} season from ESPN…");

        $result = $setUpSeason->handle($year);

        $this->components->twoColumnDetail('Weeks closed', $result['closed'] === [] ? 'none' : implode(', ', $result['closed']));
        $this->components->twoColumnDetail('Weeks in progress', $result['in_progress'] === [] ? 'none' : implode(', ', $result['in_progress']));
        $this->components->twoColumnDetail('Open for picks', $result['opened'] === null ? 'none' : "week {$result['opened']}");

        foreach ($result['notes'] as $note) {
            $this->components->warn($note);
        }

        return self::SUCCESS;
    }
}

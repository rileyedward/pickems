<?php

use App\Actions\Seasons\SetUpSeason;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\Log;

/**
 * Loads the current season from ESPN the first time the database is migrated,
 * so a fresh production install is ready to play with no manual steps.
 *
 * This depends on ESPN being reachable. If it isn't, the deploy carries on
 * and the failure is logged; run `php artisan app:setup-season` afterwards
 * (or use Admin → Seasons) to load the schedule. Tests skip it entirely so
 * they never touch the network.
 */
return new class extends Migration
{
    /**
     * The work is dozens of HTTP requests; don't hold one transaction open
     * across them. Each step commits on its own and is safe to re-run.
     */
    public $withinTransaction = false;

    /**
     * Run the migrations.
     */
    public function up(): void
    {
        if (app()->runningUnitTests()) {
            return;
        }

        try {
            app(SetUpSeason::class)->handle(SetUpSeason::currentYear());
        } catch (Throwable $exception) {
            Log::warning('Could not load the season from ESPN during migration. Run `php artisan app:setup-season` to retry.', [
                'exception' => $exception,
            ]);

            if (app()->runningInConsole()) {
                fwrite(STDERR, "  Warning: could not load the season from ESPN ({$exception->getMessage()}). Run `php artisan app:setup-season` to retry.\n");
            }
        }
    }

    /**
     * Reverse the migrations. The season data is left in place; rolling back
     * the tables that hold it removes it.
     */
    public function down(): void
    {
        //
    }
};

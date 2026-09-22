<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;

/**
 * A clean start: the current season from ESPN and no users. Create your
 * admin afterwards with `php artisan app:create-admin`.
 */
class DatabaseSeeder extends Seeder
{
    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        $this->call(SeasonSetupSeeder::class);
    }
}

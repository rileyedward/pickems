<?php

use App\Models\Entry;
use Illuminate\Database\Migrations\Migration;

/**
 * Admins only run the pool, so take them out of any week they were entered
 * into but never played. Deleting through the model clears cached results.
 */
return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Entry::whereNull('submitted_at')
            ->whereHas('user', fn ($query) => $query->where('is_admin', true))
            ->get()
            ->each(fn (Entry $entry) => $entry->delete());
    }

    /**
     * Reverse the migrations. Removed entries aren't restored.
     */
    public function down(): void
    {
        //
    }
};

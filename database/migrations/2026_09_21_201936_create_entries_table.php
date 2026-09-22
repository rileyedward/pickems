<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('entries', function (Blueprint $table) {
            $table->id();
            $table->foreignId('week_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->unsignedSmallInteger('tiebreaker_guess')->nullable();
            $table->timestamp('submitted_at')->nullable();
            $table->unsignedTinyInteger('correct_count')->nullable();
            $table->unsignedTinyInteger('placement')->nullable();
            $table->decimal('points', 6, 2)->nullable();
            $table->timestamps();

            $table->unique(['week_id', 'user_id']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('entries');
    }
};

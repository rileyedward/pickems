<?php

namespace App\Models;

use Database\Factories\EntryFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;

/**
 * A user's slot in one week: their picks and tie-breaker guess, plus the
 * stored result (placement and points) once the week is closed.
 *
 * @property int $id
 * @property int $week_id
 * @property int $user_id
 * @property int|null $tiebreaker_guess
 * @property Carbon|null $submitted_at
 * @property int|null $correct_count
 * @property int|null $placement
 * @property string|null $points
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
#[Fillable(['week_id', 'user_id', 'tiebreaker_guess', 'submitted_at', 'correct_count', 'placement', 'points'])]
class Entry extends Model
{
    /** @use HasFactory<EntryFactory> */
    use HasFactory;

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'tiebreaker_guess' => 'integer',
            'submitted_at' => 'datetime',
            'correct_count' => 'integer',
            'placement' => 'integer',
            'points' => 'decimal:2',
        ];
    }

    /**
     * @return BelongsTo<Week, $this>
     */
    public function week(): BelongsTo
    {
        return $this->belongsTo(Week::class);
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * @return HasMany<Pick, $this>
     */
    public function picks(): HasMany
    {
        return $this->hasMany(Pick::class);
    }

    /**
     * Submitted entries are the ones that get ranked and appear on the grid.
     */
    public function isSubmitted(): bool
    {
        return $this->submitted_at !== null;
    }
}

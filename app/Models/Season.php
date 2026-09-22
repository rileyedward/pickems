<?php

namespace App\Models;

use Database\Factories\SeasonFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;

/**
 * An NFL regular season (weeks 1–18) and the points each weekly placement
 * earns toward the season leaderboard.
 *
 * @property int $id
 * @property int $year
 * @property list<int> $points_table
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
#[Fillable(['year', 'points_table'])]
class Season extends Model
{
    /** @use HasFactory<SeasonFactory> */
    use HasFactory;

    public const int REGULAR_SEASON_WEEKS = 18;

    /**
     * Points for 1st, 2nd, 3rd… Placements past the end score nothing.
     */
    public const array DEFAULT_POINTS_TABLE = [10, 7, 5, 3, 2, 1, 0, 0];

    /**
     * @var array<string, mixed>
     */
    protected $attributes = [
        'points_table' => '[10,7,5,3,2,1,0,0]',
    ];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'year' => 'integer',
            'points_table' => 'array',
        ];
    }

    /**
     * @return HasMany<Week, $this>
     */
    public function weeks(): HasMany
    {
        return $this->hasMany(Week::class)->orderBy('number');
    }
}

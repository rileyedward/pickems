<?php

namespace App\Models;

use App\Enums\GameStatus;
use Database\Factories\GameFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property int $week_id
 * @property string|null $espn_event_id
 * @property int $home_team_id
 * @property int $away_team_id
 * @property Carbon $kickoff_at
 * @property int|null $home_score
 * @property int|null $away_score
 * @property GameStatus $status
 * @property bool $is_tbd_flex
 * @property bool $is_tiebreaker
 * @property bool $manual_override
 * @property-read int|null $winning_team_id
 * @property-read int|null $total_points
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
#[Fillable([
    'week_id', 'espn_event_id', 'home_team_id', 'away_team_id', 'kickoff_at',
    'home_score', 'away_score', 'status', 'is_tbd_flex', 'is_tiebreaker', 'manual_override',
])]
class Game extends Model
{
    /** @use HasFactory<GameFactory> */
    use HasFactory;

    /**
     * @var array<string, mixed>
     */
    protected $attributes = [
        'status' => 'scheduled',
        'is_tbd_flex' => false,
        'is_tiebreaker' => false,
        'manual_override' => false,
    ];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'kickoff_at' => 'datetime',
            'home_score' => 'integer',
            'away_score' => 'integer',
            'status' => GameStatus::class,
            'is_tbd_flex' => 'boolean',
            'is_tiebreaker' => 'boolean',
            'manual_override' => 'boolean',
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
     * @return BelongsTo<Team, $this>
     */
    public function homeTeam(): BelongsTo
    {
        return $this->belongsTo(Team::class, 'home_team_id');
    }

    /**
     * @return BelongsTo<Team, $this>
     */
    public function awayTeam(): BelongsTo
    {
        return $this->belongsTo(Team::class, 'away_team_id');
    }

    /**
     * @return HasMany<Pick, $this>
     */
    public function picks(): HasMany
    {
        return $this->hasMany(Pick::class);
    }

    public function isFinal(): bool
    {
        return $this->status === GameStatus::Final;
    }

    public function involvesTeam(int $teamId): bool
    {
        return $teamId === $this->home_team_id || $teamId === $this->away_team_id;
    }

    /**
     * The winner of a final game. Null until final, and null on a tie — an
     * NFL tie means nobody's pick for that game counts.
     *
     * @return Attribute<int|null, never>
     */
    protected function winningTeamId(): Attribute
    {
        return Attribute::make(get: function (): ?int {
            if (! $this->isFinal() || $this->home_score === null || $this->away_score === null) {
                return null;
            }

            return match ($this->home_score <=> $this->away_score) {
                1 => $this->home_team_id,
                -1 => $this->away_team_id,
                default => null,
            };
        });
    }

    /**
     * Combined score, used for the tie-breaker.
     *
     * @return Attribute<int|null, never>
     */
    protected function totalPoints(): Attribute
    {
        return Attribute::make(get: fn (): ?int => $this->home_score === null || $this->away_score === null
            ? null
            : $this->home_score + $this->away_score);
    }
}

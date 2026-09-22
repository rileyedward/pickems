<?php

namespace App\Models;

use App\Enums\WeekStatus;
use Database\Factories\WeekFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Support\Carbon;

/**
 * One week of pick'ems. Draft while the schedule is being confirmed, Open once
 * active users are entered, and Closed once results and points are final.
 *
 * @property int $id
 * @property int $season_id
 * @property int $number
 * @property WeekStatus $status
 * @property Carbon|null $locks_at
 * @property Carbon|null $closed_at
 * @property-read bool $is_locked
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
#[Fillable(['season_id', 'number', 'status', 'locks_at', 'closed_at'])]
class Week extends Model
{
    /** @use HasFactory<WeekFactory> */
    use HasFactory;

    /**
     * @var array<string, mixed>
     */
    protected $attributes = [
        'status' => 'draft',
    ];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'number' => 'integer',
            'status' => WeekStatus::class,
            'locks_at' => 'datetime',
            'closed_at' => 'datetime',
        ];
    }

    /**
     * @return BelongsTo<Season, $this>
     */
    public function season(): BelongsTo
    {
        return $this->belongsTo(Season::class);
    }

    /**
     * @return HasMany<Game, $this>
     */
    public function games(): HasMany
    {
        return $this->hasMany(Game::class)->orderBy('kickoff_at')->orderBy('id');
    }

    /**
     * @return HasOne<Game, $this>
     */
    public function tiebreakerGame(): HasOne
    {
        return $this->hasOne(Game::class)->where('is_tiebreaker', true);
    }

    /**
     * @return HasMany<Entry, $this>
     */
    public function entries(): HasMany
    {
        return $this->hasMany(Entry::class);
    }

    /**
     * Weeks players can see: anything that has been opened.
     *
     * @param  Builder<Week>  $query
     */
    public function scopePublished(Builder $query): void
    {
        $query->whereIn('status', [WeekStatus::Open, WeekStatus::Closed]);
    }

    /**
     * The week everyone is looking at: the open week if there is one,
     * otherwise the most recently closed week.
     */
    public static function current(): ?self
    {
        return static::published()
            ->join('seasons', 'seasons.id', '=', 'weeks.season_id')
            ->orderByDesc('seasons.year')
            ->orderByRaw("case when weeks.status = 'open' then 0 else 1 end")
            ->orderByDesc('weeks.number')
            ->select('weeks.*')
            ->first();
    }

    /**
     * The open week people make picks for, if any.
     */
    public static function currentOpen(): ?self
    {
        $week = static::current();

        return $week?->isOpen() ? $week : null;
    }

    /**
     * Picks can no longer be made or changed. Also the point at which
     * everyone's picks are revealed.
     *
     * @return Attribute<bool, never>
     */
    protected function isLocked(): Attribute
    {
        return Attribute::make(get: fn (): bool => match ($this->status) {
            WeekStatus::Closed => true,
            WeekStatus::Open => $this->locks_at !== null && now()->greaterThanOrEqualTo($this->locks_at),
            WeekStatus::Draft => false,
        });
    }

    public function isDraft(): bool
    {
        return $this->status === WeekStatus::Draft;
    }

    public function isOpen(): bool
    {
        return $this->status === WeekStatus::Open;
    }

    public function isClosed(): bool
    {
        return $this->status === WeekStatus::Closed;
    }

    /**
     * Draft, Open, Locked or Closed — the lifecycle label shown in the UI.
     */
    public function phase(): string
    {
        return match (true) {
            $this->isOpen() && $this->is_locked => 'locked',
            default => $this->status->value,
        };
    }

    /**
     * Point the week's lock at its earliest kickoff.
     */
    public function refreshLocksAt(): void
    {
        $this->locks_at = $this->games()->min('kickoff_at');
        $this->save();
    }
}

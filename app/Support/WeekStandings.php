<?php

namespace App\Support;

use App\Models\Entry;
use App\Models\Game;
use App\Models\Week;
use Illuminate\Support\Collection;

/**
 * Scores a week. Shared by the live board and by closing the week, so the
 * numbers people watch are exactly the numbers that get stored.
 *
 * Rules:
 * - Only submitted entries are ranked.
 * - A pick is correct when it names the winner of a final game. A tied NFL
 *   game has no winner, so nobody gets it.
 * - Most correct ranks first. Ties go to whoever's tie-breaker guess is
 *   closest to the tie-breaker game's combined score; anyone still level
 *   shares the placement and splits the points for the spots they occupy.
 * - Until the tie-breaker game is final, only correct counts separate people,
 *   so placements and points on an open week are projections.
 */
class WeekStandings
{
    /** @var Collection<int, Game> */
    private Collection $games;

    /** @var Collection<int, Entry> */
    private Collection $entries;

    public function __construct(private Week $week)
    {
        $week->loadMissing(['season', 'games', 'entries.user', 'entries.picks']);

        $this->games = $week->games->keyBy('id');
        $this->entries = $week->entries->filter(fn (Entry $entry) => $entry->isSubmitted())->values();
    }

    public static function for(Week $week): self
    {
        return new self($week);
    }

    /**
     * Points for a placement shared by `$tiedCount` entries: the average of
     * the table values for every spot they occupy. Spots past the end of the
     * table are worth 0.
     *
     * @param  list<int>  $table  index 0 = 1st place
     */
    public static function pointsFor(int $rank, int $tiedCount, array $table): float
    {
        $spots = range($rank - 1, $rank + max($tiedCount, 1) - 2);

        $total = array_sum(array_map(fn (int $index) => $table[$index] ?? 0, $spots));

        return round($total / count($spots), 2);
    }

    public function tiebreakerGame(): ?Game
    {
        return $this->games->first(fn (Game $game) => $game->is_tiebreaker);
    }

    /**
     * The actual combined score of the tie-breaker game, once it is final.
     */
    public function tiebreakerTotal(): ?int
    {
        $game = $this->tiebreakerGame();

        return $game?->isFinal() ? $game->total_points : null;
    }

    public function allGamesFinal(): bool
    {
        return $this->games->isNotEmpty() && $this->games->every(fn (Game $game) => $game->isFinal());
    }

    /**
     * Correct picks for one entry.
     */
    public function correctCount(Entry $entry): int
    {
        return $entry->picks
            ->filter(function ($pick) {
                $winner = $this->games->get($pick->game_id)?->winning_team_id;

                return $winner !== null && $winner === $pick->team_id;
            })
            ->count();
    }

    /**
     * Ranked submitted entries. Placement uses standard competition ranking
     * (1, 1, 3), `is_leader` marks everyone currently sharing first, and
     * `points` is what that placement earns from the season's table.
     *
     * @return Collection<int, array{entry: Entry, correct: int, tiebreaker_diff: int|null, rank: int, placement: int, is_leader: bool, points: float}>
     */
    public function rows(): Collection
    {
        $total = $this->tiebreakerTotal();

        $rows = $this->entries
            ->map(fn (Entry $entry) => [
                'entry' => $entry,
                'correct' => $this->correctCount($entry),
                'tiebreaker_diff' => $total === null || $entry->tiebreaker_guess === null
                    ? null
                    : abs($entry->tiebreaker_guess - $total),
            ])
            ->sort(fn (array $a, array $b) => [$b['correct'], $a['tiebreaker_diff'] ?? PHP_INT_MAX, $a['entry']->user->display_name]
                <=> [$a['correct'], $b['tiebreaker_diff'] ?? PHP_INT_MAX, $b['entry']->user->display_name])
            ->values();

        $ranks = [];
        $rank = 0;
        $previous = null;

        foreach ($rows as $index => $row) {
            $key = [$row['correct'], $row['tiebreaker_diff']];

            if ($key !== $previous) {
                $rank = $index + 1;
                $previous = $key;
            }

            $ranks[$index] = $rank;
        }

        $tiedCounts = array_count_values($ranks);
        $table = $this->week->season->points_table;

        return $rows->map(fn (array $row, int $index) => [
            'entry' => $row['entry'],
            'correct' => $row['correct'],
            'tiebreaker_diff' => $row['tiebreaker_diff'],
            'rank' => $ranks[$index],
            'placement' => $ranks[$index],
            'is_leader' => $ranks[$index] === 1,
            'points' => self::pointsFor($ranks[$index], $tiedCounts[$ranks[$index]], $table),
        ]);
    }

    /**
     * Everyone in first place.
     *
     * @return Collection<int, Entry>
     */
    public function winners(): Collection
    {
        return $this->rows()->where('placement', 1)->pluck('entry')->values();
    }
}

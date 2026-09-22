<?php

namespace App\Support;

use App\Models\Entry;
use App\Models\Season;
use App\Models\User;
use App\Models\Week;
use Illuminate\Support\Arr;
use Illuminate\Support\Collection;

/**
 * The season standings, built from the stored results of closed weeks.
 *
 * Sorted by total points, then weeks won (1st-place finishes, ties
 * included), then total correct picks. Rank uses competition ranking on
 * those three; the display name only orders people who are level.
 */
class SeasonLeaderboard
{
    /** @var Collection<int, Week> */
    private Collection $weeks;

    public function __construct(Season $season)
    {
        $this->weeks = $season->weeks()
            ->where('status', 'closed')
            ->with('entries.user')
            ->get();
    }

    public static function for(Season $season): self
    {
        return new self($season);
    }

    /**
     * The presented leaderboard and chart weeks, from the results cache.
     *
     * @return array{weeks: list<int>, rows: list<array<string, mixed>>}
     */
    public static function cached(Season $season): array
    {
        return ResultsCache::leaderboard($season->id, function () use ($season) {
            $leaderboard = self::for($season);

            return ['weeks' => $leaderboard->weekNumbers(), 'rows' => $leaderboard->present()];
        });
    }

    /**
     * Numbers of the closed weeks, in order: the x-axis of the points chart.
     *
     * @return list<int>
     */
    public function weekNumbers(): array
    {
        return array_values($this->weeks->map(fn (Week $week) => $week->number)->all());
    }

    /**
     * @return Collection<int, array{rank: int, user: User, total_points: float, weeks_won: int, total_correct: int, weeks_played: int, dnp_count: int, cumulative: list<float>}>
     */
    public function rows(): Collection
    {
        $users = $this->weeks->flatMap(fn (Week $week) => $week->entries)->pluck('user')->unique('id');

        $rows = $users
            ->map(function (User $user) {
                $entries = $this->weeks->map(fn (Week $week) => $week->entries->firstWhere('user_id', $user->id));
                $played = $entries->filter();

                $running = 0.0;
                $cumulative = [];

                foreach ($entries as $entry) {
                    $running += (float) ($entry->points ?? 0);
                    $cumulative[] = round($running, 2);
                }

                return [
                    'user' => $user,
                    'total_points' => round($running, 2),
                    'weeks_won' => $played->where('placement', 1)->count(),
                    'total_correct' => (int) $played->sum('correct_count'),
                    'weeks_played' => $played->filter(fn (Entry $entry) => $entry->isSubmitted())->count(),
                    'dnp_count' => $played->reject(fn (Entry $entry) => $entry->isSubmitted())->count(),
                    'cumulative' => $cumulative,
                ];
            })
            ->sort(fn (array $a, array $b) => [$b['total_points'], $b['weeks_won'], $b['total_correct'], $a['user']->display_name]
                <=> [$a['total_points'], $a['weeks_won'], $a['total_correct'], $b['user']->display_name])
            ->values();

        $ranked = collect();
        $rank = 0;
        $previous = null;

        foreach ($rows as $index => $row) {
            $key = [$row['total_points'], $row['weeks_won'], $row['total_correct']];

            if ($key !== $previous) {
                $rank = $index + 1;
                $previous = $key;
            }

            $ranked->push(['rank' => $rank] + $row);
        }

        return $ranked;
    }

    /**
     * The leaderboard as the front end's LeaderboardRow type.
     *
     * @return list<array<string, mixed>>
     */
    public function present(): array
    {
        return array_values($this->rows()
            ->map(fn (array $row) => ['user' => $row['user']->toAvatar()] + Arr::except($row, 'user'))
            ->all());
    }
}

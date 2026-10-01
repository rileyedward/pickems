<?php

namespace App\Support;

use App\Models\Entry;
use App\Models\Game;
use App\Models\User;
use App\Models\Week;

/**
 * The "master spreadsheet" for a week: games, who's in (with each submitted
 * entry's picks), and — once picks lock — live standings. Until the lock,
 * picks are only shown to viewers who have submitted their own.
 */
class WeekBoard
{
    /**
     * @return array<string, mixed>
     */
    public static function present(Week $week, User $viewer): array
    {
        // A closed week's results are final (and the same for every viewer),
        // so its board is cached.
        return $week->isClosed()
            ? ResultsCache::weekBoard($week->id, fn () => self::build($week, $viewer))
            : self::build($week, $viewer);
    }

    /**
     * @return array<string, mixed>
     */
    private static function build(Week $week, User $viewer): array
    {
        $week->loadMissing(['season', 'games.homeTeam', 'games.awayTeam', 'entries.user', 'entries.picks']);

        $standings = WeekStandings::for($week);
        $revealPicks = $week->is_locked;
        $canViewPicks = $revealPicks || self::hasSubmitted($week, $viewer);

        return [
            'week' => self::week($week),
            'can_view_picks' => $canViewPicks,
            'games' => $week->games->map(fn (Game $game) => self::game($game))->values(),
            'tiebreaker_total' => $standings->tiebreakerTotal(),
            'all_games_final' => $standings->allGamesFinal(),
            'participants' => $week->entries
                ->sortBy(fn (Entry $entry) => $entry->user->display_name)
                ->map(fn (Entry $entry) => [
                    'user' => $entry->user->toAvatar(),
                    'submitted' => $entry->isSubmitted(),
                    'submitted_at' => $entry->submitted_at?->toIso8601String(),
                    'tiebreaker_guess' => $canViewPicks && $entry->isSubmitted() ? $entry->tiebreaker_guess : null,
                    'picks' => $canViewPicks && $entry->isSubmitted() ? self::picks($entry) : [],
                ])
                ->values(),
            'standings' => $revealPicks ? self::standings($week, $standings) : null,
        ];
    }

    /**
     * Before the lock, seeing everyone's picks is the reward for getting your
     * own in. Admins don't play, and can already see picks in the admin.
     */
    private static function hasSubmitted(Week $week, User $viewer): bool
    {
        return $viewer->is_admin || $week->entries->contains(
            fn (Entry $entry) => $entry->user_id === $viewer->id && $entry->isSubmitted(),
        );
    }

    /**
     * @return array<string, mixed>
     */
    public static function week(Week $week): array
    {
        return [
            'id' => $week->id,
            'number' => $week->number,
            'season_year' => $week->season->year,
            'phase' => $week->phase(),
            'is_locked' => $week->is_locked,
            'locks_at' => $week->locks_at?->toIso8601String(),
            'closed_at' => $week->closed_at?->toIso8601String(),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public static function game(Game $game): array
    {
        return [
            'id' => $game->id,
            'kickoff_at' => $game->kickoff_at->toIso8601String(),
            'status' => $game->status->value,
            'home' => $game->homeTeam->toSummary(),
            'away' => $game->awayTeam->toSummary(),
            'home_score' => $game->home_score,
            'away_score' => $game->away_score,
            'winning_team_id' => $game->winning_team_id,
            'is_tiebreaker' => $game->is_tiebreaker,
            'is_tbd_flex' => $game->is_tbd_flex,
        ];
    }

    /**
     * @return array<int, int>
     */
    public static function picks(Entry $entry): array
    {
        return $entry->picks->mapWithKeys(fn ($pick) => [$pick->game_id => $pick->team_id])->all();
    }

    /**
     * Ranked submitted entries, then everyone who didn't play ("DNP"). Closed
     * weeks show the stored placement and points; open weeks show where
     * things stand if the week ended now.
     *
     * @return list<array<string, mixed>>
     */
    private static function standings(Week $week, WeekStandings $standings): array
    {
        $ranked = $standings->rows()->map(fn (array $row) => [
            'entry_id' => $row['entry']->id,
            'user' => $row['entry']->user->toAvatar(),
            'submitted' => true,
            'correct' => $row['correct'],
            'placement' => $week->isClosed() ? $row['entry']->placement : $row['placement'],
            'is_leader' => $row['is_leader'],
            'points' => $week->isClosed() ? (float) $row['entry']->points : $row['points'],
            'tiebreaker_guess' => $row['entry']->tiebreaker_guess,
            'tiebreaker_diff' => $row['tiebreaker_diff'],
            'picks' => self::picks($row['entry']),
        ]);

        $didNotPlay = $week->entries
            ->reject(fn (Entry $entry) => $entry->isSubmitted())
            ->sortBy(fn (Entry $entry) => $entry->user->display_name)
            ->map(fn (Entry $entry) => [
                'entry_id' => $entry->id,
                'user' => $entry->user->toAvatar(),
                'submitted' => false,
                'correct' => 0,
                'placement' => null,
                'is_leader' => false,
                'points' => 0.0,
                'tiebreaker_guess' => null,
                'tiebreaker_diff' => null,
                'picks' => [],
            ]);

        return array_values($ranked->concat($didNotPlay)->all());
    }
}

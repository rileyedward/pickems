<?php

namespace App\Support;

use App\Enums\GameStatus;
use Carbon\CarbonImmutable;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Http;

/**
 * Reads ESPN's free, unofficial NFL endpoints and flattens the parts this app
 * needs. Nothing here writes to the database; the Sync actions do that.
 *
 * @phpstan-type EspnTeam array{espn_id: string, abbreviation: string, location: string, name: string, display_name: string, color: string|null, alternate_color: string|null, logo_url: string|null}
 * @phpstan-type EspnGame array{espn_event_id: string, week: int, kickoff_at: CarbonImmutable, home: EspnTeam, away: EspnTeam, home_score: int|null, away_score: int|null, status: GameStatus, is_tbd_flex: bool}
 */
class EspnClient
{
    /**
     * All 32 teams.
     *
     * @return list<EspnTeam>
     */
    public function teams(): array
    {
        $teams = $this->request()->get('teams')->throw()->json('sports.0.leagues.0.teams', []);

        return array_values(array_map(fn (array $row) => $this->team($row['team']), $teams));
    }

    /**
     * Every regular-season game in one week, with scores once games start.
     *
     * @return list<EspnGame>
     */
    public function regularSeasonWeek(int $year, int $week): array
    {
        $events = $this->request()
            ->get('scoreboard', ['dates' => $year, 'seasontype' => 2, 'week' => $week])
            ->throw()
            ->json('events', []);

        return array_values(array_filter(array_map(fn (array $event) => $this->game($event), $events)));
    }

    private function request(): PendingRequest
    {
        return Http::baseUrl(config('services.espn.base_url'))
            ->acceptJson()
            ->timeout(15)
            ->retry(2, 500);
    }

    /**
     * @param  array<string, mixed>  $event
     * @return EspnGame|null
     */
    private function game(array $event): ?array
    {
        $competition = Arr::array($event, 'competitions.0', []);
        $competitors = collect(Arr::array($competition, 'competitors', []))->keyBy('homeAway');

        if (! $competitors->has(['home', 'away'])) {
            return null;
        }

        $status = GameStatus::fromEspnState((string) Arr::get($competition, 'status.type.state', 'pre'));

        // ESPN reports "0" for both sides before kickoff; only trust scores once play starts.
        $score = fn (string $side): ?int => $status === GameStatus::Scheduled
            ? null
            : (int) $competitors[$side]['score'];

        return [
            'espn_event_id' => (string) $event['id'],
            'week' => (int) Arr::get($event, 'week.number'),
            'kickoff_at' => CarbonImmutable::parse($event['date'])->utc(),
            'home' => $this->team($competitors['home']['team']),
            'away' => $this->team($competitors['away']['team']),
            'home_score' => $score('home'),
            'away_score' => $score('away'),
            'status' => $status,
            'is_tbd_flex' => (bool) Arr::get($competition, 'status.isTBDFlex', false),
        ];
    }

    /**
     * @param  array<string, mixed>  $team
     * @return EspnTeam
     */
    private function team(array $team): array
    {
        $logo = $team['logo']
            ?? collect(Arr::array($team, 'logos', []))->first(fn (array $logo) => in_array('default', $logo['rel'] ?? [], true))['href']
            ?? null;

        return [
            'espn_id' => (string) $team['id'],
            'abbreviation' => (string) $team['abbreviation'],
            'location' => (string) ($team['location'] ?? ''),
            'name' => (string) ($team['name'] ?? $team['displayName']),
            'display_name' => (string) $team['displayName'],
            'color' => $team['color'] ?? null,
            'alternate_color' => $team['alternateColor'] ?? null,
            'logo_url' => $logo,
        ];
    }
}

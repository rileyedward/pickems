<?php

namespace App\Actions\Nfl;

use App\Models\Team;
use App\Support\EspnClient;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;

/**
 * Create or refresh a team from ESPN data, keeping a local copy of its logo
 * so pages don't hot-link ESPN's CDN.
 *
 * @phpstan-import-type EspnTeam from EspnClient
 */
class UpsertTeam
{
    /**
     * @param  EspnTeam  $data
     */
    public function handle(array $data): Team
    {
        $team = Team::updateOrCreate(
            ['espn_id' => $data['espn_id']],
            collect($data)->only(['abbreviation', 'location', 'name', 'display_name', 'color', 'alternate_color'])->all(),
        );

        $logoMissing = blank($team->logo_path) || ! Storage::disk('public')->exists($team->logo_path);

        if ($logoMissing && filled($data['logo_url'])) {
            $team->update(['logo_path' => $this->downloadLogo($team, $data['logo_url'])]);
        }

        return $team;
    }

    private function downloadLogo(Team $team, string $url): ?string
    {
        $path = 'teams/'.strtolower($team->abbreviation).'.png';

        try {
            $response = Http::timeout(15)->get($url);
        } catch (ConnectionException) {
            return null;
        }

        if (! $response->successful()) {
            return null;
        }

        Storage::disk('public')->put($path, $response->body());

        return $path;
    }
}

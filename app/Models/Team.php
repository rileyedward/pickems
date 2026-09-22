<?php

namespace App\Models;

use Database\Factories\TeamFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Storage;

/**
 * @property int $id
 * @property string $espn_id
 * @property string $abbreviation
 * @property string $location
 * @property string $name
 * @property string $display_name
 * @property string|null $color
 * @property string|null $alternate_color
 * @property string|null $logo_path
 * @property-read string $logo_url
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
#[Fillable(['espn_id', 'abbreviation', 'location', 'name', 'display_name', 'color', 'alternate_color', 'logo_path'])]
class Team extends Model
{
    /** @use HasFactory<TeamFactory> */
    use HasFactory;

    /**
     * The locally stored logo, falling back to ESPN's CDN until a sync has
     * downloaded it (or if the stored file has gone missing).
     *
     * @return Attribute<string, never>
     */
    protected function logoUrl(): Attribute
    {
        return Attribute::make(get: fn (): string => filled($this->logo_path) && Storage::disk('public')->exists($this->logo_path)
            ? Storage::disk('public')->url($this->logo_path)
            : 'https://a.espncdn.com/i/teamlogos/nfl/500/'.strtolower($this->abbreviation).'.png');
    }

    /**
     * @return array{id: int, abbreviation: string, name: string, display_name: string, color: string|null, logo: string}
     */
    public function toSummary(): array
    {
        return [
            'id' => $this->id,
            'abbreviation' => $this->abbreviation,
            'name' => $this->name,
            'display_name' => $this->display_name,
            'color' => $this->color,
            'logo' => $this->logo_url,
        ];
    }
}

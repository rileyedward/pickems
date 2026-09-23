<?php

namespace App\Models;

use Database\Factories\UserFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Filesystem\FilesystemAdapter;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Http\UploadedFile;
use Illuminate\Notifications\Notifiable;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Storage;

/**
 * A friend's account. Anyone can register; an admin marks the account active,
 * and only active users are entered into weeks. Admins only run the pool:
 * they never play and don't appear on the public pages.
 *
 * @property int $id
 * @property string $name
 * @property string|null $nickname
 * @property string $email
 * @property Carbon|null $email_verified_at
 * @property string $password
 * @property string|null $photo_path
 * @property bool $is_admin
 * @property bool $is_active
 * @property string|null $remember_token
 * @property-read string $display_name
 * @property-read string|null $photo_url
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
#[Fillable(['name', 'nickname', 'email', 'password', 'photo_path'])]
#[Hidden(['password', 'remember_token'])]
class User extends Authenticatable
{
    /** @use HasFactory<UserFactory> */
    use HasFactory, Notifiable;

    /**
     * @var array<string, mixed>
     */
    protected $attributes = [
        'is_admin' => false,
        'is_active' => false,
    ];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
            'is_admin' => 'boolean',
            'is_active' => 'boolean',
        ];
    }

    /**
     * @return HasMany<Entry, $this>
     */
    public function entries(): HasMany
    {
        return $this->hasMany(Entry::class);
    }

    /**
     * @param  Builder<User>  $query
     */
    public function scopeActive(Builder $query): void
    {
        $query->where('is_active', true);
    }

    /**
     * Everyone who can play: active and not an admin.
     *
     * @param  Builder<User>  $query
     */
    public function scopePlayers(Builder $query): void
    {
        $query->where('is_active', true)->where('is_admin', false);
    }

    public function isPlayer(): bool
    {
        return $this->is_active && ! $this->is_admin;
    }

    /**
     * @param  Builder<User>  $query
     */
    public function scopeAlphabetical(Builder $query): void
    {
        $query->orderBy('name');
    }

    /**
     * @return Attribute<string, never>
     */
    protected function displayName(): Attribute
    {
        return Attribute::make(get: fn () => filled($this->nickname) ? $this->nickname : $this->name);
    }

    /**
     * @return Attribute<string|null, never>
     */
    protected function photoUrl(): Attribute
    {
        return Attribute::make(get: fn (): ?string => blank($this->photo_path)
            ? null
            : static::photoDisk()->url($this->photo_path));
    }

    /**
     * The avatar payload the front end's UserAvatar component expects.
     *
     * @return array{id: int, name: string, photo: string|null}
     */
    public function toAvatar(): array
    {
        return [
            'id' => $this->id,
            'name' => $this->display_name,
            'photo' => $this->photo_url,
        ];
    }

    /**
     * The disk profile photos are stored on (see filesystems.photos).
     */
    public static function photoDisk(): FilesystemAdapter
    {
        return Storage::disk(config('filesystems.photos'));
    }

    /**
     * Store a newly uploaded photo on the photo disk, replacing any old one.
     */
    public function replacePhoto(UploadedFile $photo): void
    {
        $this->removePhoto();

        $this->update(['photo_path' => $photo->storePublicly('user-photos', config('filesystems.photos'))]);
    }

    public function removePhoto(): void
    {
        if (filled($this->photo_path)) {
            static::photoDisk()->delete($this->photo_path);
        }

        $this->update(['photo_path' => null]);
    }

    /**
     * Whether this is the only admin left, who therefore can't step down or
     * delete their account.
     */
    public function isLastAdmin(): bool
    {
        return $this->is_admin && ! static::where('is_admin', true)->whereKeyNot($this->id)->exists();
    }
}

<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\MorphMany;
use Illuminate\Support\Str;

/**
 * @property int $id
 * @property string $uuid
 * @property string $name
 * @property string $name_normalized
 * @property string|null $image
 * @property string|null $musicbrainz_id
 * @property string|null $bio
 * @property \Illuminate\Support\Carbon $created_at
 * @property \Illuminate\Support\Carbon $updated_at
 * @property-read \Illuminate\Database\Eloquent\Collection<int, Album> $albums
 * @property-read \Illuminate\Database\Eloquent\Collection<int, Song> $songs
 * @property-read int $album_count
 * @property-read int $song_count
 */
class Artist extends Model
{
    /** @use HasFactory<\Database\Factories\ArtistFactory> */
    use HasFactory;

    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $fillable = [
        'name',
        'name_normalized',
        'image',
        'musicbrainz_id',
        'bio',
    ];

    /**
     * Bootstrap the model.
     */
    protected static function boot(): void
    {
        parent::boot();

        static::creating(function (Artist $artist): void {
            if (empty($artist->uuid)) {
                $artist->uuid = (string) Str::uuid();
            }
            if (empty($artist->name_normalized)) {
                $artist->name_normalized = self::normalizeName($artist->name);
            }
        });

        static::updating(function (Artist $artist): void {
            if ($artist->isDirty('name')) {
                $artist->name_normalized = self::normalizeName($artist->name);
            }
        });
    }

    /**
     * Normalize a name for searching and comparison.
     */
    public static function normalizeName(string $name): string
    {
        return Str::lower(Str::ascii($name));
    }

    /**
     * Get the artist's albums.
     *
     * @return HasMany<Album, $this>
     */
    public function albums(): HasMany
    {
        return $this->hasMany(Album::class);
    }

    /**
     * Get the artist's songs.
     *
     * @return HasMany<Song, $this>
     */
    public function songs(): HasMany
    {
        return $this->hasMany(Song::class);
    }

    /**
     * Get the artist's favorites.
     *
     * @return MorphMany<Favorite, $this>
     */
    public function favorites(): MorphMany
    {
        return $this->morphMany(Favorite::class, 'favoritable');
    }

    /**
     * Get the album count attribute.
     */
    public function getAlbumCountAttribute(): int
    {
        return $this->albums()->count();
    }

    /**
     * Get the song count attribute.
     */
    public function getSongCountAttribute(): int
    {
        return $this->songs()->count();
    }

    /**
     * Get the route key name for route model binding.
     */
    public function getRouteKeyName(): string
    {
        return 'uuid';
    }

    /**
     * Find or create an artist by name.
     */
    public static function findOrCreateByName(string $name): self
    {
        $normalized = self::normalizeName($name);

        return self::firstOrCreate(
            ['name_normalized' => $normalized],
            ['name' => $name]
        );
    }
}

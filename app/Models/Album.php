<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\MorphMany;
use Illuminate\Support\Str;
use Laravel\Scout\Searchable;

/**
 * @property int $id
 * @property string $uuid
 * @property int|null $artist_id
 * @property string $name
 * @property string $name_normalized
 * @property string|null $cover
 * @property int|null $year
 * @property string|null $musicbrainz_id
 * @property \Illuminate\Support\Carbon $created_at
 * @property \Illuminate\Support\Carbon $updated_at
 * @property-read Artist|null $artist
 * @property-read \Illuminate\Database\Eloquent\Collection<int, Song> $songs
 * @property-read int $song_count
 * @property-read float $total_length
 */
class Album extends Model
{
    /** @use HasFactory<\Database\Factories\AlbumFactory> */
    use HasFactory;
    use Searchable;

    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $fillable = [
        'artist_id',
        'name',
        'name_normalized',
        'cover',
        'year',
        'musicbrainz_id',
    ];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'year' => 'integer',
        ];
    }

    /**
     * Bootstrap the model.
     */
    protected static function boot(): void
    {
        parent::boot();

        static::creating(function (Album $album): void {
            if (empty($album->uuid)) {
                $album->uuid = (string) Str::uuid();
            }
            if (empty($album->name_normalized)) {
                $album->name_normalized = self::normalizeName($album->name);
            }
        });

        static::updating(function (Album $album): void {
            if ($album->isDirty('name')) {
                $album->name_normalized = self::normalizeName($album->name);
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
     * Get the album's artist.
     *
     * @return BelongsTo<Artist, $this>
     */
    public function artist(): BelongsTo
    {
        return $this->belongsTo(Artist::class);
    }

    /**
     * Get the album's songs.
     *
     * @return HasMany<Song, $this>
     */
    public function songs(): HasMany
    {
        return $this->hasMany(Song::class)->orderBy('disc')->orderBy('track');
    }

    /**
     * Get the album's favorites.
     *
     * @return MorphMany<Favorite, $this>
     */
    public function favorites(): MorphMany
    {
        return $this->morphMany(Favorite::class, 'favoritable');
    }

    /**
     * Get the song count attribute.
     */
    public function getSongCountAttribute(): int
    {
        return $this->songs()->count();
    }

    /**
     * Get the total length attribute.
     */
    public function getTotalLengthAttribute(): float
    {
        return (float) $this->songs()->sum('length');
    }

    /**
     * Get the artist name attribute.
     */
    public function getArtistNameAttribute(): ?string
    {
        return $this->artist?->name;
    }

    /**
     * Get the route key name for route model binding.
     */
    public function getRouteKeyName(): string
    {
        return 'uuid';
    }

    /**
     * Find or create an album by name and artist.
     */
    public static function findOrCreateByNameAndArtist(string $name, ?Artist $artist = null): self
    {
        $normalized = self::normalizeName($name);

        return self::firstOrCreate(
            [
                'name_normalized' => $normalized,
                'artist_id' => $artist?->id,
            ],
            ['name' => $name]
        );
    }

    /**
     * Get the indexable data array for the model.
     *
     * @return array<string, mixed>
     */
    public function toSearchableArray(): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'artist_id' => $this->artist_id,
            'artist_name' => $this->artist?->name,
            'year' => $this->year,
            'created_at' => $this->created_at->timestamp,
        ];
    }

    /**
     * Get the name of the index associated with the model.
     */
    public function searchableAs(): string
    {
        return 'albums';
    }
}

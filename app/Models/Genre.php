<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Support\Str;

/**
 * @property int $id
 * @property string $name
 * @property string $name_normalized
 * @property \Illuminate\Support\Carbon $created_at
 * @property-read \Illuminate\Database\Eloquent\Collection<int, Song> $songs
 */
class Genre extends Model
{
    /** @use HasFactory<\Database\Factories\GenreFactory> */
    use HasFactory;

    /**
     * Indicates if the model should be timestamped.
     *
     * @var bool
     */
    public $timestamps = false;

    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $fillable = [
        'name',
        'name_normalized',
    ];

    /**
     * Bootstrap the model.
     */
    protected static function boot(): void
    {
        parent::boot();

        static::creating(function (Genre $genre): void {
            if (empty($genre->name_normalized)) {
                $genre->name_normalized = self::normalizeName($genre->name);
            }
            if (empty($genre->created_at)) {
                $genre->created_at = now();
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
     * Get the genre's songs.
     *
     * @return BelongsToMany<Song, $this>
     */
    public function songs(): BelongsToMany
    {
        return $this->belongsToMany(Song::class);
    }

    /**
     * Find or create a genre by name.
     */
    public static function findOrCreateByName(string $name): self
    {
        $normalized = self::normalizeName($name);

        return self::firstOrCreate(
            ['name_normalized' => $normalized],
            ['name' => $name]
        );
    }

    /**
     * Sync genres from a comma-separated string.
     *
     * @return array<Genre>
     */
    public static function syncFromString(string $genreString): array
    {
        $genres = [];
        $names = array_filter(array_map('trim', explode(',', $genreString)));

        foreach ($names as $name) {
            $genres[] = self::findOrCreateByName($name);
        }

        return $genres;
    }
}

<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\AudioFormat;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\MorphMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Laravel\Scout\Searchable;

/**
 * @property string $id
 * @property int|null $album_id
 * @property int|null $artist_id
 * @property int|null $smart_folder_id
 * @property string $title
 * @property string $title_normalized
 * @property string|null $album_name
 * @property string|null $artist_name
 * @property float $length
 * @property int|null $track
 * @property int $disc
 * @property int|null $year
 * @property string|null $lyrics
 * @property string $storage_path
 * @property string|null $file_hash
 * @property int $file_size
 * @property string|null $mime_type
 * @property AudioFormat $audio_format
 * @property string|null $r2_etag
 * @property \Illuminate\Support\Carbon|null $r2_last_modified
 * @property string|null $musicbrainz_id
 * @property int|null $mtime
 * @property \Illuminate\Support\Carbon $created_at
 * @property \Illuminate\Support\Carbon $updated_at
 * @property \Illuminate\Support\Carbon|null $deleted_at
 * @property-read Album|null $album
 * @property-read Artist|null $artist
 * @property-read SmartFolder|null $smartFolder
 * @property-read \Illuminate\Database\Eloquent\Collection<int, Genre> $genres
 * @property-read \Illuminate\Database\Eloquent\Collection<int, Tag> $tags
 * @property-read \Illuminate\Database\Eloquent\Collection<int, Playlist> $playlists
 * @property-read \Illuminate\Database\Eloquent\Collection<int, Interaction> $interactions
 * @property-read \Illuminate\Database\Eloquent\Collection<int, Transcode> $transcodes
 */
class Song extends Model
{
    /** @use HasFactory<\Database\Factories\SongFactory> */
    use HasFactory;
    use HasUuids;
    use Searchable;
    use SoftDeletes;

    /**
     * The primary key for the model.
     *
     * @var string
     */
    protected $primaryKey = 'id';

    /**
     * The "type" of the primary key ID.
     *
     * @var string
     */
    protected $keyType = 'string';

    /**
     * Indicates if the IDs are auto-incrementing.
     *
     * @var bool
     */
    public $incrementing = false;

    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $fillable = [
        'album_id',
        'artist_id',
        'smart_folder_id',
        'title',
        'title_normalized',
        'album_name',
        'artist_name',
        'length',
        'track',
        'disc',
        'year',
        'lyrics',
        'storage_path',
        'file_hash',
        'file_size',
        'mime_type',
        'audio_format',
        'r2_etag',
        'r2_last_modified',
        'musicbrainz_id',
        'mtime',
    ];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'length' => 'float',
            'track' => 'integer',
            'disc' => 'integer',
            'year' => 'integer',
            'file_size' => 'integer',
            'audio_format' => AudioFormat::class,
            'r2_last_modified' => 'datetime',
            'mtime' => 'integer',
        ];
    }

    /**
     * Bootstrap the model.
     */
    protected static function boot(): void
    {
        parent::boot();

        static::creating(function (Song $song): void {
            if (empty($song->title_normalized)) {
                $song->title_normalized = self::normalizeName($song->title);
            }
        });

        static::updating(function (Song $song): void {
            if ($song->isDirty('title')) {
                $song->title_normalized = self::normalizeName($song->title);
            }
        });
    }

    /**
     * Perform model bootstrapping for event listeners.
     */
    protected static function booted(): void
    {
        // Delete the backing file from R2 storage when song is force deleted
        static::forceDeleting(function (Song $song): void {
            if ($song->storage_path) {
                Storage::disk('r2')->delete($song->storage_path);
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
     * Get the song's album.
     *
     * @return BelongsTo<Album, $this>
     */
    public function album(): BelongsTo
    {
        return $this->belongsTo(Album::class);
    }

    /**
     * Get the song's artist.
     *
     * @return BelongsTo<Artist, $this>
     */
    public function artist(): BelongsTo
    {
        return $this->belongsTo(Artist::class);
    }

    /**
     * Get the song's smart folder.
     *
     * @return BelongsTo<SmartFolder, $this>
     */
    public function smartFolder(): BelongsTo
    {
        return $this->belongsTo(SmartFolder::class);
    }

    /**
     * Get the song's genres.
     *
     * @return BelongsToMany<Genre, $this>
     */
    public function genres(): BelongsToMany
    {
        return $this->belongsToMany(Genre::class);
    }

    /**
     * Get the song's tags.
     *
     * @return BelongsToMany<Tag, $this>
     */
    public function tags(): BelongsToMany
    {
        return $this->belongsToMany(Tag::class, 'song_tag')
            ->withPivot('auto_assigned', 'created_at');
    }

    /**
     * Get the song's playlists.
     *
     * @return BelongsToMany<Playlist, $this>
     */
    public function playlists(): BelongsToMany
    {
        return $this->belongsToMany(Playlist::class)
            ->withPivot('position', 'created_at');
    }

    /**
     * Get the song's interactions.
     *
     * @return HasMany<Interaction, $this>
     */
    public function interactions(): HasMany
    {
        return $this->hasMany(Interaction::class);
    }

    /**
     * Get the song's transcodes.
     *
     * @return HasMany<Transcode, $this>
     */
    public function transcodes(): HasMany
    {
        return $this->hasMany(Transcode::class);
    }

    /**
     * Get the song's favorites.
     *
     * @return MorphMany<Favorite, $this>
     */
    public function favorites(): MorphMany
    {
        return $this->morphMany(Favorite::class, 'favoritable');
    }

    /**
     * Get the play count for a specific user.
     */
    public function getPlayCountForUser(User $user): int
    {
        return (int) $this->interactions()
            ->where('user_id', $user->id)
            ->value('play_count');
    }

    /**
     * Check if the song is a favorite for a user.
     */
    public function isFavoriteFor(User $user): bool
    {
        return $this->favorites()->where('user_id', $user->id)->exists();
    }

    /**
     * Get the formatted length as MM:SS.
     */
    public function getFormattedLengthAttribute(): string
    {
        $length = max(0, $this->length);
        $minutes = floor($length / 60);
        $seconds = (int) ($length % 60);

        return sprintf('%d:%02d', $minutes, $seconds);
    }

    /**
     * Get the genre names as a comma-separated string.
     */
    public function getGenreStringAttribute(): string
    {
        return $this->genres->pluck('name')->implode(', ');
    }

    /**
     * Find a song by storage path.
     */
    public static function findByStoragePath(string $path): ?self
    {
        return self::where('storage_path', $path)->first();
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
            'title' => $this->title,
            'artist_name' => $this->artist_name,
            'album_name' => $this->album_name,
            'artist_id' => $this->artist_id,
            'album_id' => $this->album_id,
            'smart_folder_id' => $this->smart_folder_id,
            'year' => $this->year,
            'audio_format' => $this->audio_format->value,
            'lyrics' => $this->lyrics,
            'tag_ids' => $this->tags->pluck('id')->toArray(),
            'genre_ids' => $this->genres->pluck('id')->toArray(),
            'created_at' => $this->created_at->timestamp,
        ];
    }

    /**
     * Get the name of the index associated with the model.
     */
    public function searchableAs(): string
    {
        return 'songs';
    }
}

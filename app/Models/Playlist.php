<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\MorphMany;

/**
 * @property string $id
 * @property int $user_id
 * @property string $name
 * @property string|null $description
 * @property string|null $cover
 * @property bool $is_smart
 * @property array<array{id: int, logic: string, rules: array<array{field: string, operator: string, value: mixed}>}>|null $rules
 * @property \Illuminate\Support\Carbon|null $rules_updated_at
 * @property \Illuminate\Support\Carbon|null $materialized_at
 * @property \Illuminate\Support\Carbon $created_at
 * @property \Illuminate\Support\Carbon $updated_at
 * @property-read User $user
 * @property-read \Illuminate\Database\Eloquent\Collection<int, Song> $songs
 * @property-read int $song_count
 * @property-read float $total_length
 */
class Playlist extends Model
{
    /** @use HasFactory<\Database\Factories\PlaylistFactory> */
    use HasFactory;
    use HasUuids;

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
        'user_id',
        'name',
        'description',
        'cover',
        'is_smart',
        'rules',
        'rules_updated_at',
        'materialized_at',
    ];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'is_smart' => 'boolean',
            'rules' => 'array',
            'rules_updated_at' => 'datetime',
            'materialized_at' => 'datetime',
        ];
    }

    /**
     * Bootstrap the model.
     */
    protected static function boot(): void
    {
        parent::boot();

        // Automatically set rules_updated_at when rules change
        static::saving(function (Playlist $playlist): void {
            if ($playlist->isDirty('rules')) {
                $playlist->rules_updated_at = now();
            }
        });
    }

    /**
     * Get the playlist's owner.
     *
     * @return BelongsTo<User, $this>
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Get the playlist's songs (for non-smart playlists).
     *
     * @return BelongsToMany<Song, $this>
     */
    public function songs(): BelongsToMany
    {
        return $this->belongsToMany(Song::class)
            ->withPivot('position', 'added_by', 'created_at')
            ->orderByPivot('position');
    }

    /**
     * Get the playlist's favorites.
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
        if ($this->is_smart) {
            // For smart playlists, use materialized count if available
            if ($this->materialized_at !== null) {
                return $this->songs()->count();
            }
            // Otherwise will be calculated by the evaluator in controller
            return 0;
        }

        return $this->songs()->count();
    }

    /**
     * Check if the smart playlist needs rematerialization.
     */
    public function needsRematerialization(): bool
    {
        if (!$this->is_smart) {
            return false;
        }

        // Never materialized
        if ($this->materialized_at === null) {
            return true;
        }

        // Rules were updated after last materialization
        if ($this->rules_updated_at !== null && $this->rules_updated_at > $this->materialized_at) {
            return true;
        }

        return false;
    }

    /**
     * Get the total length attribute.
     */
    public function getTotalLengthAttribute(): float
    {
        if ($this->is_smart) {
            // For smart playlists, use materialized sum if available
            if ($this->materialized_at !== null) {
                return (float) $this->songs()->sum('songs.length');
            }
            return 0;
        }

        return (float) $this->songs()->sum('songs.length');
    }

    /**
     * Add a song to the playlist at a specific position.
     */
    public function addSong(Song $song, ?int $position = null, ?User $addedBy = null): void
    {
        if ($this->is_smart) {
            throw new \RuntimeException('Cannot add songs to a smart playlist');
        }

        if ($position === null) {
            $position = $this->songs()->max('playlist_song.position') + 1;
        }

        $this->songs()->attach($song->id, [
            'position' => $position,
            'added_by' => $addedBy?->id,
            'created_at' => now(),
        ]);
    }

    /**
     * Remove a song from the playlist.
     */
    public function removeSong(Song $song): void
    {
        if ($this->is_smart) {
            throw new \RuntimeException('Cannot remove songs from a smart playlist');
        }

        $this->songs()->detach($song->id);
    }

    /**
     * Reorder songs in the playlist.
     *
     * @param array<string> $songIds
     */
    public function reorderSongs(array $songIds): void
    {
        if ($this->is_smart) {
            throw new \RuntimeException('Cannot reorder songs in a smart playlist');
        }

        foreach ($songIds as $position => $songId) {
            $this->songs()->updateExistingPivot($songId, ['position' => $position]);
        }
    }

    /**
     * Scope a query to only include smart playlists.
     *
     * @param \Illuminate\Database\Eloquent\Builder<Playlist> $query
     * @return \Illuminate\Database\Eloquent\Builder<Playlist>
     */
    public function scopeSmart(\Illuminate\Database\Eloquent\Builder $query): \Illuminate\Database\Eloquent\Builder
    {
        return $query->where('is_smart', true);
    }

    /**
     * Scope a query to only include regular playlists.
     *
     * @param \Illuminate\Database\Eloquent\Builder<Playlist> $query
     * @return \Illuminate\Database\Eloquent\Builder<Playlist>
     */
    public function scopeRegular(\Illuminate\Database\Eloquent\Builder $query): \Illuminate\Database\Eloquent\Builder
    {
        return $query->where('is_smart', false);
    }
}

<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property int $id
 * @property int $user_id
 * @property string $song_id
 * @property int $play_count
 * @property \Illuminate\Support\Carbon|null $last_played_at
 * @property \Illuminate\Support\Carbon $created_at
 * @property \Illuminate\Support\Carbon $updated_at
 * @property-read User $user
 * @property-read Song $song
 */
class Interaction extends Model
{
    /** @use HasFactory<\Database\Factories\InteractionFactory> */
    use HasFactory;

    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $fillable = [
        'user_id',
        'song_id',
        'play_count',
        'last_played_at',
    ];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'play_count' => 'integer',
            'last_played_at' => 'datetime',
        ];
    }

    /**
     * Get the user.
     *
     * @return BelongsTo<User, $this>
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Get the song.
     *
     * @return BelongsTo<Song, $this>
     */
    public function song(): BelongsTo
    {
        return $this->belongsTo(Song::class);
    }

    /**
     * Record a play for a song by a user.
     */
    public static function recordPlay(User $user, Song $song): self
    {
        // Use firstOrCreate + increment for atomic operation to handle race conditions
        $interaction = self::firstOrCreate(
            [
                'user_id' => $user->id,
                'song_id' => $song->id,
            ],
            [
                'play_count' => 0,
                'last_played_at' => now(),
            ]
        );

        $interaction->increment('play_count');
        $interaction->update(['last_played_at' => now()]);

        return $interaction->fresh() ?? $interaction;
    }

    /**
     * Get the interaction for a user and song.
     */
    public static function forUserAndSong(User $user, Song $song): ?self
    {
        return self::where('user_id', $user->id)
            ->where('song_id', $song->id)
            ->first();
    }
}

<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property string $id
 * @property int $user_id
 * @property string $name
 * @property string $type
 * @property \Illuminate\Support\Carbon $last_seen
 * @property array<string, mixed>|null $state
 * @property \Illuminate\Support\Carbon $created_at
 * @property-read User $user
 * @property-read bool $is_playing
 * @property-read string|null $current_song_id
 * @property-read float|null $position
 * @property-read float|null $volume
 */
class PlayerDevice extends Model
{
    /** @use HasFactory<\Database\Factories\PlayerDeviceFactory> */
    use HasFactory;

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
        'id',
        'user_id',
        'name',
        'type',
        'last_seen',
        'state',
    ];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'last_seen' => 'datetime',
            'created_at' => 'datetime',
            'state' => 'array',
        ];
    }

    /**
     * Bootstrap the model.
     */
    protected static function boot(): void
    {
        parent::boot();

        static::creating(function (PlayerDevice $device): void {
            if (empty($device->created_at)) {
                $device->created_at = now();
            }
            if (empty($device->last_seen)) {
                $device->last_seen = now();
            }
        });
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
     * Check if the device is currently playing.
     */
    public function getIsPlayingAttribute(): bool
    {
        return (bool) ($this->state['is_playing'] ?? false);
    }

    /**
     * Get the current song ID.
     */
    public function getCurrentSongIdAttribute(): ?string
    {
        return $this->state['song_id'] ?? null;
    }

    /**
     * Get the current position.
     */
    public function getPositionAttribute(): ?float
    {
        return isset($this->state['position']) ? (float) $this->state['position'] : null;
    }

    /**
     * Get the volume level.
     */
    public function getVolumeAttribute(): ?float
    {
        return isset($this->state['volume']) ? (float) $this->state['volume'] : null;
    }

    /**
     * Update the device state.
     *
     * @param array<string, mixed> $stateUpdate
     */
    public function updateState(array $stateUpdate): void
    {
        $state = $this->state ?? [];
        $this->update([
            'state' => array_merge($state, $stateUpdate),
            'last_seen' => now(),
        ]);
    }

    /**
     * Touch the last_seen timestamp.
     *
     * @param string|null $attribute
     */
    public function touch($attribute = null): bool
    {
        if ($attribute !== null) {
            return parent::touch($attribute);
        }

        return $this->update(['last_seen' => now()]);
    }

    /**
     * Check if the device is considered online.
     */
    public function isOnline(int $thresholdSeconds = 60): bool
    {
        return $this->last_seen->diffInSeconds(now()) < $thresholdSeconds;
    }

    /**
     * Get online devices for a user.
     *
     * @return \Illuminate\Database\Eloquent\Collection<int, PlayerDevice>
     */
    public static function onlineForUser(User $user, int $thresholdSeconds = 60): \Illuminate\Database\Eloquent\Collection
    {
        return self::where('user_id', $user->id)
            ->where('last_seen', '>=', now()->subSeconds($thresholdSeconds))
            ->get();
    }

    /**
     * Clean up stale devices.
     */
    public static function cleanupStale(int $thresholdHours = 24): int
    {
        return self::where('last_seen', '<', now()->subHours($thresholdHours))->delete();
    }
}

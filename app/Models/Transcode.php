<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\AudioFormat;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property int $id
 * @property string $song_id
 * @property string $format
 * @property int $bitrate
 * @property string $storage_key
 * @property int $file_size
 * @property \Illuminate\Support\Carbon $created_at
 * @property-read Song $song
 */
class Transcode extends Model
{
    /** @use HasFactory<\Database\Factories\TranscodeFactory> */
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
        'song_id',
        'format',
        'bitrate',
        'storage_key',
        'file_size',
    ];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'bitrate' => 'integer',
            'file_size' => 'integer',
            'created_at' => 'datetime',
        ];
    }

    /**
     * Bootstrap the model.
     */
    protected static function boot(): void
    {
        parent::boot();

        static::creating(function (Transcode $transcode): void {
            if (empty($transcode->created_at)) {
                $transcode->created_at = now();
            }
        });
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
     * Get the audio format enum.
     */
    public function getAudioFormat(): ?AudioFormat
    {
        return AudioFormat::tryFrom($this->format);
    }

    /**
     * Find a transcode for a song with specific format and bitrate.
     */
    public static function findForSong(Song $song, string $format, int $bitrate): ?self
    {
        return self::where('song_id', $song->id)
            ->where('format', $format)
            ->where('bitrate', $bitrate)
            ->first();
    }

    /**
     * Generate a storage key for a transcode.
     */
    public static function generateStorageKey(Song $song, string $format, int $bitrate): string
    {
        return sprintf('transcodes/%s/%s_%d.%s', $song->id, $format, $bitrate, $format);
    }
}

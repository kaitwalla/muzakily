<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\DownloadRequestStatus;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property string $id
 * @property int $user_id
 * @property string $url
 * @property list<int> $tag_ids
 * @property DownloadRequestStatus $status
 * @property string|null $song_id
 * @property string|null $error
 * @property \Illuminate\Support\Carbon|null $created_at
 * @property \Illuminate\Support\Carbon|null $updated_at
 * @property-read User $user
 * @property-read Song|null $song
 */
class DownloadRequest extends Model
{
    use HasUuids;

    /**
     * @var list<string>
     */
    protected $fillable = [
        'user_id',
        'url',
        'tag_ids',
        'status',
        'song_id',
        'error',
    ];

    /**
     * @var array<string, string>
     */
    protected $casts = [
        'tag_ids' => 'array',
        'status' => DownloadRequestStatus::class,
    ];

    /**
     * @return BelongsTo<User, $this>
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * @return BelongsTo<Song, $this>
     */
    public function song(): BelongsTo
    {
        return $this->belongsTo(Song::class);
    }
}

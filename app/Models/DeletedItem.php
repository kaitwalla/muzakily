<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property string $deletable_type
 * @property string $deletable_id
 * @property int|null $user_id
 * @property Carbon $deleted_at
 * @property-read User|null $user
 */
class DeletedItem extends Model
{
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
        'deletable_type',
        'deletable_id',
        'user_id',
        'deleted_at',
    ];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'deleted_at' => 'datetime',
        ];
    }

    /**
     * Get the user who owned the deleted item (for playlists).
     *
     * @return BelongsTo<User, $this>
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Scope a query to filter by deletable type.
     *
     * @param Builder<DeletedItem> $query
     * @return Builder<DeletedItem>
     */
    public function scopeOfType(Builder $query, string $type): Builder
    {
        return $query->where('deletable_type', $type);
    }

    /**
     * Scope a query to filter by deletion time.
     *
     * @param Builder<DeletedItem> $query
     * @return Builder<DeletedItem>
     */
    public function scopeSince(Builder $query, Carbon $since): Builder
    {
        return $query->where('deleted_at', '>=', $since);
    }

    /**
     * Scope a query to filter by user (for playlists).
     *
     * @param Builder<DeletedItem> $query
     * @return Builder<DeletedItem>
     */
    public function scopeForUser(Builder $query, ?User $user): Builder
    {
        if ($user === null) {
            return $query->whereNull('user_id');
        }

        return $query->where('user_id', $user->id);
    }

    /**
     * Record a deletion.
     */
    public static function recordDeletion(string $type, string $id, ?int $userId = null): self
    {
        return self::create([
            'deletable_type' => $type,
            'deletable_id' => $id,
            'user_id' => $userId,
            'deleted_at' => now(),
        ]);
    }
}

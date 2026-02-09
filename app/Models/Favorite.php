<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;

/**
 * @property int $id
 * @property int $user_id
 * @property string $favoritable_type
 * @property string $favoritable_id
 * @property \Illuminate\Support\Carbon $created_at
 * @property-read User $user
 * @property-read Song|Album|Artist|Playlist $favoritable
 */
class Favorite extends Model
{
    /** @use HasFactory<\Database\Factories\FavoriteFactory> */
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
        'user_id',
        'favoritable_type',
        'favoritable_id',
    ];

    /**
     * Bootstrap the model.
     */
    protected static function boot(): void
    {
        parent::boot();

        static::creating(function (Favorite $favorite): void {
            if (empty($favorite->created_at)) {
                $favorite->created_at = now();
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
     * Get the favoritable model.
     *
     * @return MorphTo<Model, $this>
     */
    public function favoritable(): MorphTo
    {
        return $this->morphTo();
    }

    /**
     * Add a favorite for a user.
     */
    public static function add(User $user, Model $model): self
    {
        return self::firstOrCreate([
            'user_id' => $user->id,
            'favoritable_type' => $model->getMorphClass(),
            'favoritable_id' => $model->getKey(),
        ]);
    }

    /**
     * Remove a favorite for a user.
     */
    public static function remove(User $user, Model $model): bool
    {
        return (bool) self::where([
            'user_id' => $user->id,
            'favoritable_type' => $model->getMorphClass(),
            'favoritable_id' => $model->getKey(),
        ])->delete();
    }

    /**
     * Toggle a favorite for a user.
     */
    public static function toggle(User $user, Model $model): bool
    {
        $exists = self::where([
            'user_id' => $user->id,
            'favoritable_type' => $model->getMorphClass(),
            'favoritable_id' => $model->getKey(),
        ])->exists();

        if ($exists) {
            self::remove($user, $model);
            return false;
        }

        self::add($user, $model);
        return true;
    }

    /**
     * Check if a model is favorited by a user.
     */
    public static function isFavorited(User $user, Model $model): bool
    {
        return self::where([
            'user_id' => $user->id,
            'favoritable_type' => $model->getMorphClass(),
            'favoritable_id' => $model->getKey(),
        ])->exists();
    }

    /**
     * Get the morph map for favoritable types.
     *
     * @return array<string, class-string<Model>>
     */
    public static function morphMap(): array
    {
        return [
            'song' => Song::class,
            'album' => Album::class,
            'artist' => Artist::class,
            'playlist' => Playlist::class,
        ];
    }
}

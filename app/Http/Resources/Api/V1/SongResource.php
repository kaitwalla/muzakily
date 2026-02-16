<?php

declare(strict_types=1);

namespace App\Http\Resources\Api\V1;

use App\Models\Song;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin Song
 */
class SongResource extends JsonResource
{
    /**
     * The expected JSON structure for contract tests.
     *
     * @var array<string>
     */
    public static array $jsonStructure = [
        'id',
        'title',
        'artist_id',
        'artist_name',
        'artist_slug',
        'album_id',
        'album_name',
        'album_slug',
        'album_cover',
        'length',
        'track',
        'disc',
        'year',
        'genre',
        'audio_format',
        'is_favorite',
        'play_count',
        'tags',
        'storage_path',
        'created_at',
    ];

    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        /** @var User|null $user */
        $user = $request->user();

        return [
            'id' => $this->id,
            'title' => $this->title,
            'artist_id' => $this->artist?->uuid,
            'artist_name' => $this->artist_name,
            'artist_slug' => $this->artist?->uuid,
            'album_id' => $this->album?->uuid,
            'album_name' => $this->album_name,
            'album_slug' => $this->album?->uuid,
            'album_cover' => $this->album?->cover,
            'length' => $this->length,
            'track' => $this->track,
            'disc' => $this->disc,
            'year' => $this->year,
            'genre' => $this->genre_string,
            'audio_format' => $this->audio_format->value,
            'is_favorite' => $user ? $this->isFavoriteFor($user) : false,
            'play_count' => $user ? $this->getPlayCountForUser($user) : 0,
            'tags' => $this->when(
                $this->relationLoaded('tags'),
                fn () => $this->tags->map(fn ($tag): array => [
                    'id' => $tag->id,
                    'name' => $tag->name,
                    'slug' => $tag->slug,
                    'color' => $tag->color,
                ])->all()
            ),
            'storage_path' => $this->storage_path,
            'created_at' => $this->created_at->toIso8601String(),
        ];
    }
}

<?php

declare(strict_types=1);

namespace App\Http\Resources\Api\V1;

use App\Models\Artist;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin Artist
 */
class ArtistResource extends JsonResource
{
    /**
     * The expected JSON structure for contract tests.
     *
     * @var array<string>
     */
    public static array $jsonStructure = [
        'id',
        'name',
        'image',
        'album_count',
        'song_count',
        'created_at',
    ];

    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->uuid,
            'name' => $this->name,
            'image' => $this->image,
            'bio' => $this->when($request->routeIs('artists.show'), $this->bio),
            'album_count' => $this->album_count,
            'song_count' => $this->song_count,
            'musicbrainz_id' => $this->when($request->routeIs('artists.show'), $this->musicbrainz_id),
            'created_at' => $this->created_at->toIso8601String(),
        ];
    }
}

<?php

declare(strict_types=1);

namespace App\Http\Resources\Api\V1;

use App\Models\Album;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin Album
 */
class AlbumResource extends JsonResource
{
    /**
     * The expected JSON structure for contract tests.
     *
     * @var array<string>
     */
    public static array $jsonStructure = [
        'id',
        'name',
        'artist_id',
        'artist_name',
        'cover',
        'year',
        'song_count',
        'total_length',
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
            'artist_id' => $this->artist?->uuid,
            'artist_name' => $this->artist?->name,
            'cover' => $this->cover,
            'year' => $this->year,
            'song_count' => $this->song_count,
            'total_length' => $this->total_length,
            'created_at' => $this->created_at->toIso8601String(),
        ];
    }
}

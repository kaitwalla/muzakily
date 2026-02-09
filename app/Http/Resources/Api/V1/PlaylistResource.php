<?php

declare(strict_types=1);

namespace App\Http\Resources\Api\V1;

use App\Models\Playlist;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin Playlist
 */
class PlaylistResource extends JsonResource
{
    /**
     * The expected JSON structure for contract tests.
     *
     * @var array<string>
     */
    public static array $jsonStructure = [
        'id',
        'name',
        'description',
        'cover',
        'is_smart',
        'song_count',
        'total_length',
        'created_at',
        'updated_at',
    ];

    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'description' => $this->description,
            'cover' => $this->cover,
            'is_smart' => $this->is_smart,
            'rules' => $this->when($this->is_smart, $this->rules),
            'song_count' => $this->song_count,
            'total_length' => $this->total_length,
            'created_at' => $this->created_at->toIso8601String(),
            'updated_at' => $this->updated_at->toIso8601String(),
        ];
    }
}

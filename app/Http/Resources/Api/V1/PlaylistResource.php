<?php

declare(strict_types=1);

namespace App\Http\Resources\Api\V1;

use App\Models\Playlist;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Support\Facades\Storage;

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
        'slug',
        'name',
        'description',
        'cover_url',
        'user_id',
        'is_public',
        'is_smart',
        'songs_count',
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
            'slug' => $this->id, // Use UUID as slug for routing
            'name' => $this->name,
            'description' => $this->description,
            'cover_url' => $this->cover ? Storage::disk('public')->url($this->cover) : null,
            'user_id' => $this->user_id,
            'is_public' => false, // TODO: Add is_public column when needed
            'is_smart' => $this->is_smart,
            'rules' => $this->when($this->is_smart, $this->rules),
            'songs_count' => $this->is_smart ? ($this->smart_song_count ?? 0) : $this->song_count,
            'total_length' => $this->is_smart ? ($this->smart_total_length ?? 0) : $this->total_length,
            'created_at' => $this->created_at->toIso8601String(),
            'updated_at' => $this->updated_at->toIso8601String(),
        ];
    }
}

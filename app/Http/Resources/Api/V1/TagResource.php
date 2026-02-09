<?php

declare(strict_types=1);

namespace App\Http\Resources\Api\V1;

use App\Models\Tag;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin Tag
 */
class TagResource extends JsonResource
{
    /**
     * The expected JSON structure for contract tests.
     *
     * @var array<string>
     */
    public static array $jsonStructure = [
        'id',
        'name',
        'slug',
        'color',
        'song_count',
        'parent_id',
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
            'id' => $this->id,
            'name' => $this->name,
            'slug' => $this->slug,
            'color' => $this->color,
            'song_count' => $this->song_count,
            'parent_id' => $this->parent_id,
            'depth' => $this->when($request->routeIs('tags.show'), $this->depth),
            'is_special' => $this->when($request->routeIs('tags.show'), $this->is_special),
            'auto_assign_pattern' => $this->when($request->routeIs('tags.show'), $this->auto_assign_pattern),
            'children' => $this->when(
                $this->relationLoaded('children') && $this->children->isNotEmpty(),
                fn () => TagResource::collection($this->children)
            ),
            'created_at' => $this->created_at->toIso8601String(),
        ];
    }
}

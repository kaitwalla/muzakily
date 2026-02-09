<?php

declare(strict_types=1);

namespace App\Http\Resources\Api\V1;

use App\Models\SmartFolder;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin SmartFolder
 */
class SmartFolderResource extends JsonResource
{
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
            'path' => $this->path_prefix,
            'depth' => $this->depth,
            'song_count' => $this->song_count,
            'parent_id' => $this->parent_id,
            'children' => SmartFolderResource::collection($this->whenLoaded('children')),
        ];
    }
}

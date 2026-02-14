<?php

declare(strict_types=1);

namespace App\Actions\Favorites;

use App\Exceptions\ResourceNotFoundException;
use App\Models\Album;
use App\Models\Artist;
use App\Models\Playlist;
use App\Models\Song;

final readonly class ResolveFavoritableModel
{
    /**
     * Resolve a model from type and ID.
     *
     * @throws ResourceNotFoundException
     */
    public function execute(string $type, string $id): Song|Album|Artist|Playlist
    {
        $model = match ($type) {
            'song' => Song::find($id),
            'album' => Album::where('uuid', $id)->first(),
            'artist' => Artist::where('uuid', $id)->first(),
            'playlist' => Playlist::find($id),
            default => throw new \InvalidArgumentException("Invalid favoriteable type: {$type}"),
        };

        if (!$model) {
            throw new ResourceNotFoundException('Resource not found');
        }

        return $model;
    }
}

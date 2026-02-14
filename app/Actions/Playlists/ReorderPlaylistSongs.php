<?php

declare(strict_types=1);

namespace App\Actions\Playlists;

use App\Exceptions\SmartPlaylistModificationException;
use App\Models\Playlist;

final readonly class ReorderPlaylistSongs
{
    /**
     * Reorder songs in a playlist.
     *
     * @param array<string> $songIds
     * @throws SmartPlaylistModificationException
     */
    public function execute(Playlist $playlist, array $songIds): Playlist
    {
        if ($playlist->is_smart) {
            throw new SmartPlaylistModificationException('Cannot reorder songs in a smart playlist');
        }

        $playlist->reorderSongs($songIds);

        $playlist->refresh();

        return $playlist;
    }
}

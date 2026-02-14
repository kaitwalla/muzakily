<?php

declare(strict_types=1);

namespace App\Actions\Playlists;

use App\Exceptions\SmartPlaylistModificationException;
use App\Models\Playlist;
use App\Models\Song;

final readonly class RemoveSongsFromPlaylist
{
    /**
     * Remove songs from a playlist.
     *
     * @param array<string> $songIds
     * @throws SmartPlaylistModificationException
     */
    public function execute(Playlist $playlist, array $songIds): Playlist
    {
        if ($playlist->is_smart) {
            throw new SmartPlaylistModificationException('Cannot remove songs from a smart playlist');
        }

        foreach ($songIds as $songId) {
            $song = Song::find($songId);
            if ($song) {
                $playlist->removeSong($song);
            }
        }

        $playlist->refresh();

        return $playlist;
    }
}

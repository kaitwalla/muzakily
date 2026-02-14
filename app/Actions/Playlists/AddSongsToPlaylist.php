<?php

declare(strict_types=1);

namespace App\Actions\Playlists;

use App\Exceptions\SmartPlaylistModificationException;
use App\Models\Playlist;
use App\Models\Song;
use App\Models\User;
use Illuminate\Support\Facades\DB;

final readonly class AddSongsToPlaylist
{
    /**
     * Add songs to a playlist.
     *
     * @param array<string> $songIds
     * @throws SmartPlaylistModificationException
     */
    public function execute(Playlist $playlist, array $songIds, ?int $position, User $user): Playlist
    {
        if ($playlist->is_smart) {
            throw new SmartPlaylistModificationException('Cannot add songs to a smart playlist');
        }

        DB::transaction(function () use ($playlist, $songIds, &$position, $user): void {
            $songs = Song::whereIn('id', $songIds)->get()->keyBy('id');

            foreach ($songIds as $songId) {
                $song = $songs->get($songId);
                if ($song) {
                    $playlist->addSong($song, $position, $user);
                    if ($position !== null) {
                        $position++;
                    }
                }
            }
        });

        $playlist->refresh();

        return $playlist;
    }
}

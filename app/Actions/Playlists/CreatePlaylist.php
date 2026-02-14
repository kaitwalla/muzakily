<?php

declare(strict_types=1);

namespace App\Actions\Playlists;

use App\Models\Playlist;
use App\Models\Song;
use App\Models\User;
use App\Services\Playlist\PlaylistCoverService;

final readonly class CreatePlaylist
{
    public function __construct(
        private PlaylistCoverService $coverService,
    ) {}

    /**
     * Create a new playlist.
     *
     * @param array{name: string, description?: string|null, is_smart?: bool, rules?: array<mixed>|null, song_ids?: array<string>|null} $data
     */
    public function execute(User $user, array $data): Playlist
    {
        $songIds = $data['song_ids'] ?? null;
        unset($data['song_ids']);

        /** @var Playlist $playlist */
        $playlist = $user->playlists()->create($data);

        // If songs were provided, add them
        if ($songIds) {
            foreach ($songIds as $position => $songId) {
                $song = Song::find($songId);
                if ($song) {
                    $playlist->addSong($song, $position, $user);
                }
            }
        }

        // For smart playlists, fetch a cover image from Unsplash
        if ($playlist->is_smart) {
            try {
                $this->coverService->fetchAndStore($playlist);
            } catch (\Throwable $e) {
                // Log the error but don't fail playlist creation
                report($e);
            }
        }

        return $playlist;
    }
}

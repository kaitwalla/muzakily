<?php

declare(strict_types=1);

namespace App\Actions\Playlists;

use App\Models\Playlist;
use App\Services\Playlist\PlaylistCoverService;

final readonly class RefreshPlaylistCover
{
    public function __construct(
        private PlaylistCoverService $coverService,
    ) {}

    /**
     * Refresh the cover image for a smart playlist.
     *
     * @return bool True if cover was refreshed successfully
     * @throws \InvalidArgumentException If playlist is not a smart playlist
     */
    public function execute(Playlist $playlist): bool
    {
        if (!$playlist->is_smart) {
            throw new \InvalidArgumentException('Cover refresh is only available for smart playlists');
        }

        return $this->coverService->fetchAndStore($playlist, true);
    }
}

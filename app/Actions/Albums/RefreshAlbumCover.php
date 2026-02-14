<?php

declare(strict_types=1);

namespace App\Actions\Albums;

use App\Models\Album;
use App\Services\Library\CoverArtService;
use App\Services\Metadata\MusicBrainzService;

final readonly class RefreshAlbumCover
{
    public function __construct(
        private MusicBrainzService $musicBrainz,
        private CoverArtService $coverService,
    ) {}

    /**
     * Refresh the cover image for an album by fetching from MusicBrainz.
     *
     * @return bool True if a new cover was fetched and stored
     */
    public function execute(Album $album): bool
    {
        // Try to find cover art via MusicBrainz
        $coverUrl = $this->fetchCoverUrl($album);

        if ($coverUrl === null) {
            return false;
        }

        // Download and store to R2
        $storedUrl = $this->coverService->storeFromUrl($album, $coverUrl);

        if ($storedUrl === null) {
            return false;
        }

        $album->update(['cover' => $storedUrl]);

        return true;
    }

    /**
     * Fetch cover URL from MusicBrainz by searching for album tracks.
     */
    private function fetchCoverUrl(Album $album): ?string
    {
        // Get a representative song from the album to search
        $song = $album->songs()->first();
        if ($song === null) {
            return null;
        }

        $result = $this->musicBrainz->search(
            $song->title,
            $song->artist_name ?? $album->artist?->name,
            $album->name
        );

        if ($result === null || $result['album_cover'] === null) {
            return null;
        }

        // Update album's MusicBrainz ID if found and not set
        if (isset($result['album_mbid']) && $album->musicbrainz_id === null) {
            $album->update(['musicbrainz_id' => $result['album_mbid']]);
        }

        return $result['album_cover'];
    }
}

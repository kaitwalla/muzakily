<?php

declare(strict_types=1);

namespace App\Services\Metadata;

use App\Models\Song;
use App\Services\Library\CoverArtService;

class MetadataAggregatorService
{
    public function __construct(
        private ?MusicBrainzService $musicBrainz = null,
        private ?CoverArtService $coverArtService = null,
    ) {
        if ($this->musicBrainz === null && config('muzakily.metadata.musicbrainz.enabled', true)) {
            $this->musicBrainz = new MusicBrainzService();
        }
        if ($this->coverArtService === null) {
            $this->coverArtService = new CoverArtService();
        }
    }

    /**
     * Enrich metadata for a song from external sources.
     */
    public function enrich(Song $song): void
    {
        // Skip if already has MusicBrainz ID
        if ($song->musicbrainz_id) {
            return;
        }

        $this->enrichFromMusicBrainz($song);
    }

    /**
     * Enrich from MusicBrainz.
     */
    private function enrichFromMusicBrainz(Song $song): void
    {
        if (!$this->musicBrainz) {
            return;
        }

        $result = $this->musicBrainz->search(
            $song->title,
            $song->artist_name,
            $song->album_name
        );

        if (!$result) {
            return;
        }

        $updates = [];

        if ($result['musicbrainz_id'] !== null) {
            $updates['musicbrainz_id'] = $result['musicbrainz_id'];
        }

        if ($updates) {
            $song->update($updates);
        }

        // Update artist if we have MusicBrainz info
        if ($song->artist && ($result['artist_mbid'] ?? null)) {
            $song->artist->update([
                'musicbrainz_id' => $result['artist_mbid'],
                'bio' => $result['artist_bio'] ?? $song->artist->bio,
            ]);
        }

        // Update album if we have MusicBrainz info
        if ($song->album && ($result['album_mbid'] ?? null)) {
            $albumUpdates = ['musicbrainz_id' => $result['album_mbid']];

            // Download and store cover art to R2 if we found one and album doesn't have one
            if (($result['album_cover'] ?? null) && !$song->album->cover) {
                $storedCoverUrl = $this->coverArtService?->storeFromUrl($song->album, $result['album_cover']);
                if ($storedCoverUrl !== null) {
                    $albumUpdates['cover'] = $storedCoverUrl;
                }
            }

            $song->album->update($albumUpdates);
        }
    }
}

<?php

declare(strict_types=1);

namespace App\Services\Metadata;

use App\Contracts\MusicStorageInterface;
use App\Models\Song;
use App\Services\Library\CoverArtService;

class MetadataAggregatorService
{
    public function __construct(
        private ?MusicBrainzService $musicBrainz = null,
        private ?CoverArtService $coverArtService = null,
        private ?AcoustIdService $acoustId = null,
        private ?MusicStorageInterface $storage = null,
    ) {
        if ($this->musicBrainz === null && config('muzakily.metadata.musicbrainz.enabled', true)) {
            $this->musicBrainz = new MusicBrainzService();
        }
        if ($this->coverArtService === null) {
            $this->coverArtService = new CoverArtService();
        }
        if ($this->acoustId === null && config('muzakily.metadata.acoustid.enabled', true)) {
            $apiKey = config('muzakily.metadata.acoustid.api_key');
            $minScore = (float) config('muzakily.metadata.acoustid.min_score', 0.5);
            if ($apiKey) {
                $this->acoustId = new AcoustIdService($apiKey, $minScore);
            }
        }
        if ($this->storage === null) {
            $this->storage = app(MusicStorageInterface::class);
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

        // Try text search first if the song has a title or artist
        if (!empty($song->title) || !empty($song->artist_name)) {
            $result = $this->musicBrainz?->search(
                $song->title ?? '',
                $song->artist_name,
                $song->album_name,
            );

            if ($result) {
                $this->applyResult($song, $result);
                return;
            }
        }

        // Fall back to acoustic fingerprinting
        $this->enrichFromAcoustId($song);
    }

    /**
     * Fingerprint the song's audio file via AcoustID, then fetch full metadata
     * from MusicBrainz using the matched recording ID.
     */
    private function enrichFromAcoustId(Song $song): void
    {
        if (!$this->acoustId || !$this->musicBrainz || !$this->storage) {
            return;
        }

        $tmpPath = tempnam(sys_get_temp_dir(), 'acoustid_');

        if ($tmpPath === false) {
            return;
        }

        try {
            if (!$this->storage->download($song->storage_path, $tmpPath)) {
                return;
            }

            $recordingId = $this->acoustId->lookup($tmpPath);

            if ($recordingId === null) {
                return;
            }

            $result = $this->musicBrainz->lookupRecording($recordingId);

            if ($result) {
                $this->applyResult($song, $result);
            }
        } finally {
            if (file_exists($tmpPath)) {
                unlink($tmpPath);
            }
        }
    }

    /**
     * Apply an enrichment result to the song, artist, and album records.
     *
     * @param array{musicbrainz_id?: string|null, title?: string|null, artist_name?: string|null, artist_mbid?: string|null, album_name?: string|null, album_mbid?: string|null, album_cover?: string|null, artist_bio?: string|null} $result
     */
    private function applyResult(Song $song, array $result): void
    {
        $songUpdates = [];

        if (!empty($result['musicbrainz_id'])) {
            $songUpdates['musicbrainz_id'] = $result['musicbrainz_id'];
        }

        if (empty($song->title) && !empty($result['title'])) {
            $songUpdates['title'] = $result['title'];
        }

        // Keep denormalized song fields in sync with what MusicBrainz returns
        if (empty($song->artist_name) && !empty($result['artist_name'])) {
            $songUpdates['artist_name'] = $result['artist_name'];
        }

        if (empty($song->album_name) && !empty($result['album_name'])) {
            $songUpdates['album_name'] = $result['album_name'];
        }

        if ($songUpdates) {
            $song->update($songUpdates);
        }

        // Update artist model
        if ($song->artist && !empty($result['artist_mbid'])) {
            $artistUpdates = ['musicbrainz_id' => $result['artist_mbid']];

            if (empty($song->artist->name) && !empty($result['artist_name'])) {
                $artistUpdates['name'] = $result['artist_name'];
            }

            if (!empty($result['artist_bio'])) {
                $artistUpdates['bio'] = $result['artist_bio'];
            }

            $song->artist->update($artistUpdates);
        }

        // Update album model
        if ($song->album && !empty($result['album_mbid'])) {
            $albumUpdates = ['musicbrainz_id' => $result['album_mbid']];

            if (empty($song->album->name) && !empty($result['album_name'])) {
                $albumUpdates['name'] = $result['album_name'];
            }

            if (!empty($result['album_cover']) && !$song->album->cover) {
                $storedCoverUrl = $this->coverArtService?->storeFromUrl($song->album, $result['album_cover']);
                if ($storedCoverUrl !== null) {
                    $albumUpdates['cover'] = $storedCoverUrl;
                }
            }

            $song->album->update($albumUpdates);
        }
    }
}

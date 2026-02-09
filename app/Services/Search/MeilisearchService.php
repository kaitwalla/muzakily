<?php

declare(strict_types=1);

namespace App\Services\Search;

use App\Models\Album;
use App\Models\Artist;
use App\Models\Song;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Meilisearch\Client;
use Meilisearch\Exceptions\CommunicationException;

class MeilisearchService
{
    public function __construct(
        private Client $client,
    ) {}

    /**
     * Check if Meilisearch is available.
     */
    public function isAvailable(): bool
    {
        try {
            return $this->client->isHealthy();
        } catch (CommunicationException) {
            return false;
        }
    }

    /**
     * Perform a global search across songs, albums, and artists.
     *
     * @param array<string, mixed> $options
     * @return array<string, mixed>
     */
    public function search(string $query, array $options = []): array
    {
        $limit = (int) ($options['limit'] ?? 10);
        $type = $options['type'] ?? null;
        $filters = $options['filters'] ?? [];

        $results = [];

        if (!$type || $type === 'song') {
            $songResults = $this->searchSongs($query, $filters, $limit);
            $results['songs'] = [
                'data' => $songResults->items(),
                'total' => $songResults->total(),
            ];
        }

        if (!$type || $type === 'album') {
            $albumResults = $this->searchAlbums($query, $limit);
            $results['albums'] = [
                'data' => $albumResults->items(),
                'total' => $albumResults->total(),
            ];
        }

        if (!$type || $type === 'artist') {
            $artistResults = $this->searchArtists($query, $limit);
            $results['artists'] = [
                'data' => $artistResults->items(),
                'total' => $artistResults->total(),
            ];
        }

        $results['meta'] = [
            'query' => $query,
            'engine' => 'meilisearch',
        ];

        return $results;
    }

    /**
     * Search songs with optional filters.
     *
     * @param array<string, mixed> $filters
     * @return LengthAwarePaginator<int, Song>
     */
    public function searchSongs(string $query, array $filters = [], int $limit = 50): LengthAwarePaginator
    {
        $search = Song::search($query);

        // Apply filters using Scout's whereIn/where methods
        if (!empty($filters['year'])) {
            $search->where('year', (int) $filters['year']);
        }

        if (!empty($filters['artist_id'])) {
            $search->where('artist_id', $filters['artist_id']);
        }

        if (!empty($filters['album_id'])) {
            $search->where('album_id', $filters['album_id']);
        }

        if (!empty($filters['format'])) {
            $search->where('audio_format', strtolower($filters['format']));
        }

        if (!empty($filters['smart_folder_id'])) {
            $search->where('smart_folder_id', (int) $filters['smart_folder_id']);
        }

        // Tag filter - need to use whereIn for array field
        if (!empty($filters['tag_id'])) {
            $search->whereIn('tag_ids', [(int) $filters['tag_id']]);
        }

        // Genre filter
        if (!empty($filters['genre_id'])) {
            $search->whereIn('genre_ids', [(int) $filters['genre_id']]);
        }

        return $search->paginate($limit);
    }

    /**
     * Search albums.
     *
     * @return LengthAwarePaginator<int, Album>
     */
    public function searchAlbums(string $query, int $limit = 10): LengthAwarePaginator
    {
        return Album::search($query)->paginate($limit);
    }

    /**
     * Search artists.
     *
     * @return LengthAwarePaginator<int, Artist>
     */
    public function searchArtists(string $query, int $limit = 10): LengthAwarePaginator
    {
        return Artist::search($query)->paginate($limit);
    }

    /**
     * Reindex all models.
     */
    public function reindexAll(): void
    {
        // Clear existing indexes
        Song::removeAllFromSearch();
        Album::removeAllFromSearch();
        Artist::removeAllFromSearch();

        // Reimport - use makeAllSearchable() which is available on the model
        Song::makeAllSearchable();
        Album::makeAllSearchable();
        Artist::makeAllSearchable();
    }

    /**
     * Get index statistics.
     *
     * @return array<string, mixed>
     */
    public function getStats(): array
    {
        try {
            $songsIndex = $this->client->index('songs');
            $albumsIndex = $this->client->index('albums');
            $artistsIndex = $this->client->index('artists');

            return [
                'songs' => $songsIndex->stats(),
                'albums' => $albumsIndex->stats(),
                'artists' => $artistsIndex->stats(),
            ];
        } catch (CommunicationException) {
            return [];
        }
    }
}

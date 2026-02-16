<?php

declare(strict_types=1);

namespace App\Services\Search;

use App\Http\Resources\Api\V1\AlbumResource;
use App\Http\Resources\Api\V1\ArtistResource;
use App\Http\Resources\Api\V1\SongResource;
use App\Models\Album;
use App\Models\Artist;
use App\Models\Song;
use Illuminate\Support\Collection;

class PostgresSearchService
{
    /**
     * Perform a global search across songs, albums, and artists using PostgreSQL.
     *
     * @param array<string, mixed> $options
     * @return array<string, mixed>
     */
    public function search(string $query, array $options = []): array
    {
        $limit = (int) ($options['limit'] ?? 10);
        $type = $options['type'] ?? null;
        $filters = $options['filters'] ?? [];
        $escapedQuery = $this->escapeLike($query);

        $results = [];

        if (!$type || $type === 'song') {
            $results['songs'] = $this->searchSongs($escapedQuery, $filters, $limit);
        }

        if (!$type || $type === 'album') {
            $results['albums'] = $this->searchAlbums($escapedQuery, $limit);
        }

        if (!$type || $type === 'artist') {
            $results['artists'] = $this->searchArtists($escapedQuery, $limit);
        }

        $results['meta'] = [
            'query' => $query,
            'engine' => 'postgresql',
        ];

        return $results;
    }

    /**
     * Search songs with filters.
     *
     * @param array<string, mixed> $filters
     * @return array<string, mixed>
     */
    private function searchSongs(string $escapedQuery, array $filters, int $limit): array
    {
        $query = Song::query()
            ->where(function ($q) use ($escapedQuery) {
                $q->where('title', 'ilike', "%{$escapedQuery}%")
                    ->orWhere('artist_name', 'ilike', "%{$escapedQuery}%")
                    ->orWhere('album_name', 'ilike', "%{$escapedQuery}%");
            })
            ->with(['artist', 'album', 'genres', 'tags']);

        // Apply filters
        if (!empty($filters['year'])) {
            $query->where('year', (int) $filters['year']);
        }

        if (!empty($filters['artist_id'])) {
            $query->where('artist_id', $filters['artist_id']);
        }

        if (!empty($filters['album_id'])) {
            $query->where('album_id', $filters['album_id']);
        }

        if (!empty($filters['format'])) {
            $query->where('audio_format', strtolower($filters['format']));
        }

        if (!empty($filters['smart_folder_id'])) {
            $query->where('smart_folder_id', (int) $filters['smart_folder_id']);
        }

        // Tag filter by slug
        if (!empty($filters['tag'])) {
            $query->whereHas('tags', function ($q) use ($filters) {
                $q->where('slug', $filters['tag']);
            });
        }

        // Genre filter by name
        if (!empty($filters['genre'])) {
            $query->whereHas('genres', function ($q) use ($filters) {
                $q->where('name', 'ilike', $filters['genre']);
            });
        }

        $total = $query->count();
        $songs = $query->limit($limit)->get();

        return [
            'data' => $songs,
            'total' => $total,
        ];
    }

    /**
     * Search albums.
     *
     * @return array<string, mixed>
     */
    private function searchAlbums(string $escapedQuery, int $limit): array
    {
        $query = Album::query()
            ->where('name', 'ilike', "%{$escapedQuery}%")
            ->with('artist');

        $total = $query->count();
        $albums = $query->limit($limit)->get();

        return [
            'data' => $albums,
            'total' => $total,
        ];
    }

    /**
     * Search artists.
     *
     * @return array<string, mixed>
     */
    private function searchArtists(string $escapedQuery, int $limit): array
    {
        $query = Artist::query()
            ->where('name', 'ilike', "%{$escapedQuery}%");

        $total = $query->count();
        $artists = $query->limit($limit)->get();

        return [
            'data' => $artists,
            'total' => $total,
        ];
    }

    /**
     * Escape LIKE metacharacters.
     */
    private function escapeLike(string $value): string
    {
        return str_replace(['\\', '%', '_'], ['\\\\', '\\%', '\\_'], $value);
    }
}

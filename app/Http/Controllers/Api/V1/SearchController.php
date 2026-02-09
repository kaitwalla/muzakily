<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Resources\Api\V1\AlbumResource;
use App\Http\Resources\Api\V1\ArtistResource;
use App\Http\Resources\Api\V1\SongResource;
use App\Services\Search\SearchService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class SearchController extends Controller
{
    public function __construct(
        private SearchService $searchService,
    ) {}

    /**
     * Search songs, albums, and artists.
     */
    public function search(Request $request): JsonResponse
    {
        $request->validate([
            'q' => ['required', 'string', 'min:2'],
            'type' => ['nullable', 'string', 'in:song,album,artist'],
            'limit' => ['nullable', 'integer', 'min:1', 'max:50'],
            'filters' => ['nullable', 'array'],
            'filters.year' => ['nullable', 'integer'],
            'filters.tag' => ['nullable', 'string'],
            'filters.genre' => ['nullable', 'string'],
            'filters.format' => ['nullable', 'string', 'in:MP3,AAC,FLAC,mp3,aac,flac'],
            'filters.artist_id' => ['nullable', 'string'],
            'filters.album_id' => ['nullable', 'string'],
        ]);

        $query = $request->input('q');
        $options = [
            'type' => $request->input('type'),
            'limit' => (int) $request->input('limit', 10),
            'filters' => $request->input('filters', []),
        ];

        $results = $this->searchService->search($query, $options);

        // Transform the results using resources
        $data = [];

        if (isset($results['songs'])) {
            $data['songs'] = [
                'data' => SongResource::collection($results['songs']['data']),
                'total' => $results['songs']['total'],
            ];
        }

        if (isset($results['albums'])) {
            $data['albums'] = [
                'data' => AlbumResource::collection($results['albums']['data']),
                'total' => $results['albums']['total'],
            ];
        }

        if (isset($results['artists'])) {
            $data['artists'] = [
                'data' => ArtistResource::collection($results['artists']['data']),
                'total' => $results['artists']['total'],
            ];
        }

        return response()->json([
            'data' => $data,
            'meta' => $results['meta'] ?? [],
        ]);
    }
}

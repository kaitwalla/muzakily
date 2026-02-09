<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Resources\Api\V1\AlbumResource;
use App\Http\Resources\Api\V1\ArtistResource;
use App\Http\Resources\Api\V1\SongResource;
use App\Models\Album;
use App\Models\Artist;
use App\Models\Song;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class SearchController extends Controller
{
    /**
     * Search songs, albums, and artists.
     */
    public function search(Request $request): JsonResponse
    {
        $request->validate([
            'q' => ['required', 'string', 'min:2'],
            'type' => ['nullable', 'string', 'in:song,album,artist'],
            'limit' => ['nullable', 'integer', 'min:1', 'max:50'],
        ]);

        $query = $request->input('q');
        $escapedQuery = $this->escapeLike($query);
        $type = $request->input('type');
        $limit = (int) $request->input('limit', 10);

        $data = [];

        // Search songs
        if (!$type || $type === 'song') {
            $songs = Song::where(function ($q) use ($escapedQuery) {
                $q->where('title', 'ilike', "%{$escapedQuery}%")
                    ->orWhere('artist_name', 'ilike', "%{$escapedQuery}%")
                    ->orWhere('album_name', 'ilike', "%{$escapedQuery}%");
            })
                ->with(['artist', 'album', 'smartFolder', 'genres'])
                ->limit($limit)
                ->get();

            $data['songs'] = [
                'data' => SongResource::collection($songs),
                'total' => Song::where(function ($q) use ($escapedQuery) {
                    $q->where('title', 'ilike', "%{$escapedQuery}%")
                        ->orWhere('artist_name', 'ilike', "%{$escapedQuery}%")
                        ->orWhere('album_name', 'ilike', "%{$escapedQuery}%");
                })->count(),
            ];
        }

        // Search albums
        if (!$type || $type === 'album') {
            $albums = Album::where('name', 'ilike', "%{$escapedQuery}%")
                ->with('artist')
                ->limit($limit)
                ->get();

            $data['albums'] = [
                'data' => AlbumResource::collection($albums),
                'total' => Album::where('name', 'ilike', "%{$escapedQuery}%")->count(),
            ];
        }

        // Search artists
        if (!$type || $type === 'artist') {
            $artists = Artist::where('name', 'ilike', "%{$escapedQuery}%")
                ->limit($limit)
                ->get();

            $data['artists'] = [
                'data' => ArtistResource::collection($artists),
                'total' => Artist::where('name', 'ilike', "%{$escapedQuery}%")->count(),
            ];
        }

        return response()->json(['data' => $data]);
    }

    /**
     * Escape LIKE metacharacters.
     */
    private function escapeLike(string $value): string
    {
        return str_replace(['\\', '%', '_'], ['\\\\', '\\%', '\\_'], $value);
    }
}

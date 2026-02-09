<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Resources\Api\V1\AlbumResource;
use App\Http\Resources\Api\V1\SongResource;
use App\Models\Album;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Http\JsonResponse;

class AlbumController extends Controller
{
    /**
     * Display a listing of albums.
     */
    public function index(Request $request): AnonymousResourceCollection
    {
        $query = Album::query()->with('artist');

        // Search
        if ($search = $request->input('search')) {
            $query->where('name', 'ilike', "%{$search}%");
        }

        // Filters
        if ($artistId = $request->input('artist_id')) {
            $query->whereHas('artist', fn ($q) => $q->where('uuid', $artistId));
        }

        if ($year = $request->input('year')) {
            $query->where('year', $year);
        }

        // Sorting
        $sortField = $request->input('sort', 'name');
        $sortOrder = $request->input('order', 'asc');
        $allowedSorts = ['name', 'year', 'created_at'];

        if (in_array($sortField, $allowedSorts, true)) {
            $query->orderBy($sortField, $sortOrder);
        }

        $perPage = min((int) $request->input('per_page', 50), 100);

        return AlbumResource::collection($query->paginate($perPage));
    }

    /**
     * Display the specified album.
     */
    public function show(Album $album): JsonResponse
    {
        $album->load('artist');

        return response()->json([
            'data' => new AlbumResource($album),
        ]);
    }

    /**
     * Get songs for the specified album.
     */
    public function songs(Album $album): AnonymousResourceCollection
    {
        $songs = $album->songs()
            ->with(['artist', 'smartFolder', 'genres'])
            ->orderBy('disc')
            ->orderBy('track')
            ->get();

        return SongResource::collection($songs);
    }
}

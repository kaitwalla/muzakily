<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Resources\Api\V1\AlbumResource;
use App\Http\Resources\Api\V1\ArtistResource;
use App\Http\Resources\Api\V1\SongResource;
use App\Models\Artist;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Http\JsonResponse;

class ArtistController extends Controller
{
    /**
     * Display a listing of artists.
     */
    public function index(Request $request): AnonymousResourceCollection
    {
        $query = Artist::query();

        // Search
        if ($search = $request->input('search')) {
            $query->where('name', 'ilike', "%{$search}%");
        }

        // Sorting
        $sortField = $request->input('sort', 'name');
        $sortOrder = in_array(strtolower($request->input('order', 'asc')), ['asc', 'desc'], true)
            ? strtolower($request->input('order', 'asc'))
            : 'asc';
        $allowedSorts = ['name', 'created_at'];

        if (in_array($sortField, $allowedSorts, true)) {
            $query->orderBy($sortField, $sortOrder);
        }

        $perPage = min((int) $request->input('per_page', 50), 100);

        return ArtistResource::collection($query->paginate($perPage));
    }

    /**
     * Display the specified artist.
     */
    public function show(Artist $artist): JsonResponse
    {
        return response()->json([
            'data' => new ArtistResource($artist),
        ]);
    }

    /**
     * Get albums for the specified artist.
     */
    public function albums(Artist $artist): AnonymousResourceCollection
    {
        $albums = $artist->albums()
            ->orderBy('year', 'desc')
            ->orderBy('name')
            ->get();

        return AlbumResource::collection($albums);
    }

    /**
     * Get songs for the specified artist.
     */
    public function songs(Request $request, Artist $artist): AnonymousResourceCollection
    {
        $query = $artist->songs()
            ->with(['album', 'smartFolder', 'genres']);

        // Sorting
        $sortField = $request->input('sort', 'album_name');
        $sortOrder = in_array(strtolower($request->input('order', 'asc')), ['asc', 'desc'], true)
            ? strtolower($request->input('order', 'asc'))
            : 'asc';

        if ($sortField === 'album_name') {
            $query->orderBy('album_name', $sortOrder)
                ->orderBy('disc')
                ->orderBy('track');
        } else {
            $query->orderBy('title', $sortOrder);
        }

        $perPage = min((int) $request->input('per_page', 50), 100);

        return SongResource::collection($query->paginate($perPage));
    }
}

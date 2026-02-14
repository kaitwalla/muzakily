<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1;

use App\Actions\Albums\RefreshAlbumCover;
use App\Actions\Albums\UploadAlbumCover;
use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\UploadAlbumCoverRequest;
use App\Http\Resources\Api\V1\AlbumResource;
use App\Http\Resources\Api\V1\SongResource;
use App\Models\Album;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Http\JsonResponse;

class AlbumController extends Controller
{
    public function __construct(
        private readonly UploadAlbumCover $uploadAlbumCoverAction,
        private readonly RefreshAlbumCover $refreshAlbumCoverAction,
    ) {}
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

    /**
     * Upload a custom cover image for an album.
     */
    public function uploadCover(UploadAlbumCoverRequest $request, Album $album): JsonResponse
    {
        /** @var \Illuminate\Http\UploadedFile $file */
        $file = $request->file('cover');

        $success = $this->uploadAlbumCoverAction->execute($album, $file);

        if (!$success) {
            return response()->json([
                'error' => [
                    'code' => 'COVER_UPLOAD_FAILED',
                    'message' => 'Failed to upload cover image',
                ],
            ], 500);
        }

        return response()->json([
            'data' => new AlbumResource($album->fresh()),
        ]);
    }

    /**
     * Refresh the cover image for an album from external sources.
     */
    public function refreshCover(Album $album): JsonResponse
    {
        $success = $this->refreshAlbumCoverAction->execute($album);

        if (!$success) {
            return response()->json([
                'error' => [
                    'code' => 'COVER_FETCH_FAILED',
                    'message' => 'Could not find or download cover art for this album',
                ],
            ], 404);
        }

        return response()->json([
            'data' => new AlbumResource($album->fresh()),
        ]);
    }
}

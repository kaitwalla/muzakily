<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\UpdateSongRequest;
use App\Http\Resources\Api\V1\SongResource;
use App\Models\Interaction;
use App\Models\Song;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Http\JsonResponse;

class SongController extends Controller
{
    /**
     * Display a listing of songs.
     */
    public function index(Request $request): AnonymousResourceCollection
    {
        $query = Song::query()
            ->with(['artist', 'album', 'smartFolder', 'genres', 'tags']);

        // Search
        if ($search = $request->input('search')) {
            $query->where(function ($q) use ($search) {
                $q->where('title', 'ilike', "%{$search}%")
                    ->orWhere('artist_name', 'ilike', "%{$search}%")
                    ->orWhere('album_name', 'ilike', "%{$search}%");
            });
        }

        // Filters
        if ($artistId = $request->input('artist_id')) {
            $query->whereHas('artist', fn ($q) => $q->where('uuid', $artistId));
        }

        if ($albumId = $request->input('album_id')) {
            $query->whereHas('album', fn ($q) => $q->where('uuid', $albumId));
        }

        if ($genre = $request->input('genre')) {
            $query->whereHas('genres', fn ($q) => $q->where('name', $genre));
        }

        if ($smartFolderId = $request->input('smart_folder_id')) {
            $query->where('smart_folder_id', $smartFolderId);
        }

        if ($format = $request->input('format')) {
            $query->where('audio_format', $format);
        }

        if ($request->boolean('favorited') && $request->user()) {
            $query->whereHas('favorites', fn ($q) => $q->where('user_id', $request->user()->id));
        }

        // Sorting
        $sortField = $request->input('sort', 'title');
        $sortOrder = in_array(strtolower($request->input('order', 'asc')), ['asc', 'desc'], true)
            ? strtolower($request->input('order', 'asc'))
            : 'asc';
        $allowedSorts = ['title', 'artist_name', 'album_name', 'year', 'created_at'];

        if (in_array($sortField, $allowedSorts, true)) {
            $query->orderBy($sortField, $sortOrder);
        }

        $perPage = min((int) $request->input('per_page', 50), 100);

        return SongResource::collection($query->paginate($perPage));
    }

    /**
     * Display the specified song.
     */
    public function show(Song $song): JsonResponse
    {
        $song->load(['artist', 'album', 'smartFolder', 'genres', 'tags']);

        return response()->json([
            'data' => new SongResource($song),
        ]);
    }

    /**
     * Update the specified song.
     */
    public function update(UpdateSongRequest $request, Song $song): JsonResponse
    {
        $this->authorize('update', $song);

        $song->update($request->validated());

        return response()->json([
            'data' => new SongResource($song->fresh(['artist', 'album', 'smartFolder', 'genres', 'tags'])),
        ]);
    }

    /**
     * Get recently played songs for the authenticated user.
     */
    public function recentlyPlayed(Request $request): JsonResponse
    {
        $limit = min((int) $request->input('limit', 10), 50);

        $interactions = Interaction::where('user_id', $request->user()?->id)
            ->whereNotNull('last_played_at')
            ->orderBy('last_played_at', 'desc')
            ->limit($limit)
            ->with(['song.artist', 'song.album'])
            ->get();

        $songs = $interactions
            ->map(fn (Interaction $interaction) => $interaction->song)
            ->filter()
            ->values();

        return response()->json([
            'data' => SongResource::collection($songs),
        ]);
    }
}

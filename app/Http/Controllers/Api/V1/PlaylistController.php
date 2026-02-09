<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\CreatePlaylistRequest;
use App\Http\Requests\Api\V1\UpdatePlaylistRequest;
use App\Http\Requests\Api\V1\AddPlaylistSongsRequest;
use App\Http\Requests\Api\V1\RemovePlaylistSongsRequest;
use App\Http\Requests\Api\V1\ReorderPlaylistSongsRequest;
use App\Http\Resources\Api\V1\PlaylistResource;
use App\Http\Resources\Api\V1\SongResource;
use App\Models\Playlist;
use App\Models\Song;
use App\Services\Playlist\SmartPlaylistEvaluator;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Http\JsonResponse;

class PlaylistController extends Controller
{
    public function __construct(
        private SmartPlaylistEvaluator $smartPlaylistEvaluator,
    ) {}

    /**
     * Display a listing of the user's playlists.
     */
    public function index(Request $request): AnonymousResourceCollection
    {
        $playlists = $request->user()
            ->playlists()
            ->orderBy('name')
            ->get();

        return PlaylistResource::collection($playlists);
    }

    /**
     * Store a newly created playlist.
     */
    public function store(CreatePlaylistRequest $request): JsonResponse
    {
        $playlist = $request->user()->playlists()->create($request->validated());

        // If songs were provided, add them
        if ($songIds = $request->validated('song_ids')) {
            foreach ($songIds as $position => $songId) {
                $song = Song::find($songId);
                if ($song) {
                    $playlist->addSong($song, $position, $request->user());
                }
            }
        }

        return response()->json([
            'data' => new PlaylistResource($playlist),
        ], 201);
    }

    /**
     * Display the specified playlist.
     */
    public function show(Playlist $playlist): JsonResponse
    {
        $this->authorize('view', $playlist);

        return response()->json([
            'data' => new PlaylistResource($playlist),
        ]);
    }

    /**
     * Update the specified playlist.
     */
    public function update(UpdatePlaylistRequest $request, Playlist $playlist): JsonResponse
    {
        $this->authorize('update', $playlist);

        $playlist->update($request->validated());

        return response()->json([
            'data' => new PlaylistResource($playlist->fresh()),
        ]);
    }

    /**
     * Remove the specified playlist.
     */
    public function destroy(Playlist $playlist): JsonResponse
    {
        $this->authorize('delete', $playlist);

        $playlist->delete();

        return response()->json(null, 204);
    }

    /**
     * Get songs for the specified playlist.
     */
    public function songs(Request $request, Playlist $playlist): AnonymousResourceCollection
    {
        $this->authorize('view', $playlist);

        if ($playlist->is_smart) {
            $songs = $this->smartPlaylistEvaluator->evaluate($playlist, $request->user());
        } else {
            $songs = $playlist->songs()
                ->with(['artist', 'album', 'smartFolder', 'genres'])
                ->get();
        }

        return SongResource::collection($songs);
    }

    /**
     * Add songs to the playlist.
     */
    public function addSongs(AddPlaylistSongsRequest $request, Playlist $playlist): JsonResponse
    {
        $this->authorize('update', $playlist);

        if ($playlist->is_smart) {
            return response()->json([
                'error' => [
                    'code' => 'INVALID_OPERATION',
                    'message' => 'Cannot add songs to a smart playlist',
                ],
            ], 400);
        }

        $position = $request->validated('position');

        foreach ($request->validated('song_ids') as $songId) {
            $song = Song::find($songId);
            if ($song) {
                $playlist->addSong($song, $position, $request->user());
                if ($position !== null) {
                    $position++;
                }
            }
        }

        return response()->json([
            'data' => new PlaylistResource($playlist->fresh()),
        ]);
    }

    /**
     * Remove songs from the playlist.
     */
    public function removeSongs(RemovePlaylistSongsRequest $request, Playlist $playlist): JsonResponse
    {
        $this->authorize('update', $playlist);

        if ($playlist->is_smart) {
            return response()->json([
                'error' => [
                    'code' => 'INVALID_OPERATION',
                    'message' => 'Cannot remove songs from a smart playlist',
                ],
            ], 400);
        }

        foreach ($request->validated('song_ids') as $songId) {
            $song = Song::find($songId);
            if ($song) {
                $playlist->removeSong($song);
            }
        }

        return response()->json([
            'data' => new PlaylistResource($playlist->fresh()),
        ]);
    }

    /**
     * Reorder songs in the playlist.
     */
    public function reorderSongs(ReorderPlaylistSongsRequest $request, Playlist $playlist): JsonResponse
    {
        $this->authorize('update', $playlist);

        if ($playlist->is_smart) {
            return response()->json([
                'error' => [
                    'code' => 'INVALID_OPERATION',
                    'message' => 'Cannot reorder songs in a smart playlist',
                ],
            ], 400);
        }

        $playlist->reorderSongs($request->validated('song_ids'));

        return response()->json([
            'data' => new PlaylistResource($playlist->fresh()),
        ]);
    }
}

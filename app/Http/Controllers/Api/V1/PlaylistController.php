<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1;

use App\Actions\Playlists\AddSongsToPlaylist;
use App\Actions\Playlists\CreatePlaylist;
use App\Actions\Playlists\RefreshPlaylistCover;
use App\Actions\Playlists\RemoveSongsFromPlaylist;
use App\Actions\Playlists\ReorderPlaylistSongs;
use App\Actions\Playlists\UploadPlaylistCover;
use App\Exceptions\SmartPlaylistModificationException;
use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\CreatePlaylistRequest;
use App\Http\Requests\Api\V1\UpdatePlaylistRequest;
use App\Http\Requests\Api\V1\AddPlaylistSongsRequest;
use App\Http\Requests\Api\V1\RemovePlaylistSongsRequest;
use App\Http\Requests\Api\V1\ReorderPlaylistSongsRequest;
use App\Http\Requests\Api\V1\UploadPlaylistCoverRequest;
use App\Http\Resources\Api\V1\PlaylistResource;
use App\Http\Resources\Api\V1\SongResource;
use App\Jobs\RefreshSmartPlaylistJob;
use App\Models\Playlist;
use App\Services\Playlist\SmartPlaylistEvaluator;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Carbon;

class PlaylistController extends Controller
{
    public function __construct(
        private readonly CreatePlaylist $createPlaylist,
        private readonly AddSongsToPlaylist $addSongsToPlaylist,
        private readonly RemoveSongsFromPlaylist $removeSongsFromPlaylist,
        private readonly ReorderPlaylistSongs $reorderPlaylistSongs,
        private readonly RefreshPlaylistCover $refreshPlaylistCoverAction,
        private readonly UploadPlaylistCover $uploadPlaylistCoverAction,
        private readonly SmartPlaylistEvaluator $smartPlaylistEvaluator,
    ) {}

    /**
     * Display a listing of the user's playlists.
     */
    public function index(Request $request): AnonymousResourceCollection
    {
        $query = $request->user()
            ->playlists()
            ->withCount('songs');

        // Filter by updated_since for incremental sync
        if ($updatedSince = $request->input('updated_since')) {
            try {
                $query->where('updated_at', '>=', Carbon::parse($updatedSince));
            } catch (\Carbon\Exceptions\InvalidFormatException) {
                abort(422, 'Invalid date format for updated_since parameter');
            }
        }

        $playlists = $query->orderBy('name')->get();

        // Calculate song counts for smart playlists
        foreach ($playlists as $playlist) {
            if ($playlist->is_smart) {
                // Use materialized count (from withCount) if available
                if ($playlist->materialized_at !== null) {
                    $playlist->setAttribute('smart_song_count', $playlist->songs_count);
                } else {
                    $playlist->setAttribute('smart_song_count', $this->smartPlaylistEvaluator->count($playlist, $request->user()));
                }
            }
        }

        return PlaylistResource::collection($playlists);
    }

    /**
     * Store a newly created playlist.
     */
    public function store(CreatePlaylistRequest $request): JsonResponse
    {
        $playlist = $this->createPlaylist->execute($request->user(), $request->validated());

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

        $limit = max(1, min($request->integer('limit', 75), 500));
        $offset = max($request->integer('offset', 0), 0);

        if ($playlist->is_smart) {
            // Use materialized results if available and up-to-date
            if ($playlist->materialized_at !== null && !$playlist->needsRematerialization()) {
                $query = $playlist->songs()->with(['artist', 'album', 'genres', 'tags']);
                $total = $query->count();
                $songs = $query->skip($offset)->take($limit)->get();
            } else {
                // Dynamic evaluation with pagination
                $result = $this->smartPlaylistEvaluator->evaluatePaginated(
                    $playlist,
                    $request->user(),
                    $limit,
                    $offset
                );
                $songs = $result['songs'];
                $total = $result['total'];
            }
        } else {
            $query = $playlist->songs()->with(['artist', 'album', 'genres', 'tags']);
            $total = $query->count();
            $songs = $query->skip($offset)->take($limit)->get();
        }

        return SongResource::collection($songs)->additional([
            'meta' => [
                'total' => $total,
                'limit' => $limit,
                'offset' => $offset,
                'has_more' => ($offset + $songs->count()) < $total,
            ],
        ]);
    }

    /**
     * Add songs to the playlist.
     */
    public function addSongs(AddPlaylistSongsRequest $request, Playlist $playlist): JsonResponse
    {
        $this->authorize('update', $playlist);

        try {
            $playlist = $this->addSongsToPlaylist->execute(
                $playlist,
                $request->validated('song_ids'),
                $request->validated('position'),
                $request->user()
            );
        } catch (SmartPlaylistModificationException $e) {
            return response()->json([
                'error' => [
                    'code' => $e->getErrorCode(),
                    'message' => $e->getMessage(),
                ],
            ], $e->getStatusCode());
        }

        return response()->json([
            'data' => new PlaylistResource($playlist),
        ]);
    }

    /**
     * Remove songs from the playlist.
     */
    public function removeSongs(RemovePlaylistSongsRequest $request, Playlist $playlist): JsonResponse
    {
        $this->authorize('update', $playlist);

        try {
            $playlist = $this->removeSongsFromPlaylist->execute(
                $playlist,
                $request->validated('song_ids')
            );
        } catch (SmartPlaylistModificationException $e) {
            return response()->json([
                'error' => [
                    'code' => $e->getErrorCode(),
                    'message' => $e->getMessage(),
                ],
            ], $e->getStatusCode());
        }

        return response()->json([
            'data' => new PlaylistResource($playlist),
        ]);
    }

    /**
     * Reorder songs in the playlist.
     */
    public function reorderSongs(ReorderPlaylistSongsRequest $request, Playlist $playlist): JsonResponse
    {
        $this->authorize('update', $playlist);

        try {
            $playlist = $this->reorderPlaylistSongs->execute(
                $playlist,
                $request->validated('song_ids')
            );
        } catch (SmartPlaylistModificationException $e) {
            return response()->json([
                'error' => [
                    'code' => $e->getErrorCode(),
                    'message' => $e->getMessage(),
                ],
            ], $e->getStatusCode());
        }

        return response()->json([
            'data' => new PlaylistResource($playlist),
        ]);
    }

    /**
     * Refresh the cover image for a smart playlist.
     */
    public function refreshCover(Playlist $playlist): JsonResponse
    {
        $this->authorize('update', $playlist);

        if (!$playlist->is_smart) {
            return response()->json([
                'error' => [
                    'code' => 'INVALID_OPERATION',
                    'message' => 'Cover refresh is only available for smart playlists',
                ],
            ], 422);
        }

        $success = $this->refreshPlaylistCoverAction->execute($playlist);

        if (!$success) {
            return response()->json([
                'error' => [
                    'code' => 'COVER_FETCH_FAILED',
                    'message' => 'Failed to fetch a new cover image',
                ],
            ], 500);
        }

        return response()->json([
            'data' => new PlaylistResource($playlist->fresh()),
        ]);
    }

    /**
     * Upload a custom cover image for a playlist.
     */
    public function uploadCover(UploadPlaylistCoverRequest $request, Playlist $playlist): JsonResponse
    {
        $this->authorize('update', $playlist);

        /** @var \Illuminate\Http\UploadedFile $file */
        $file = $request->file('cover');

        $success = $this->uploadPlaylistCoverAction->execute($playlist, $file);

        if (!$success) {
            return response()->json([
                'error' => [
                    'code' => 'COVER_UPLOAD_FAILED',
                    'message' => 'Failed to upload cover image',
                ],
            ], 500);
        }

        return response()->json([
            'data' => new PlaylistResource($playlist->fresh()),
        ]);
    }

    /**
     * Refresh the songs in a smart playlist by re-evaluating rules.
     */
    public function refresh(Playlist $playlist): JsonResponse
    {
        $this->authorize('update', $playlist);

        if (!$playlist->is_smart) {
            return response()->json([
                'error' => [
                    'code' => 'INVALID_OPERATION',
                    'message' => 'Refresh is only available for smart playlists',
                ],
            ], 422);
        }

        RefreshSmartPlaylistJob::dispatch($playlist);

        return response()->json([
            'data' => new PlaylistResource($playlist),
            'message' => 'Playlist refresh has been queued',
        ]);
    }
}

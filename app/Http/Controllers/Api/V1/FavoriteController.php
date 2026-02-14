<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1;

use App\Actions\Favorites\AddFavorite;
use App\Actions\Favorites\RemoveFavorite;
use App\Actions\Favorites\ResolveFavoritableModel;
use App\Exceptions\ResourceNotFoundException;
use App\Http\Controllers\Controller;
use App\Http\Resources\Api\V1\AlbumResource;
use App\Http\Resources\Api\V1\ArtistResource;
use App\Http\Resources\Api\V1\PlaylistResource;
use App\Http\Resources\Api\V1\SongResource;
use App\Models\Album;
use App\Models\Artist;
use App\Models\Favorite;
use App\Models\Playlist;
use App\Models\Song;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class FavoriteController extends Controller
{
    public function __construct(
        private readonly ResolveFavoritableModel $resolveFavoritableModel,
        private readonly AddFavorite $addFavorite,
        private readonly RemoveFavorite $removeFavorite,
    ) {}
    /**
     * Display the user's favorites.
     */
    public function index(Request $request): JsonResponse
    {
        $user = $request->user();
        $type = $request->input('type');

        $data = [];

        // Fetch favorite IDs by type and use whereIn to avoid PostgreSQL type mismatch
        // (morph ID is stored as varchar but primary keys can be int/bigint/uuid)

        if (!$type || $type === 'song') {
            $songIds = Favorite::where('user_id', $user->id)
                ->where('favoritable_type', Song::class)
                ->pluck('favoritable_id');
            $songs = Song::whereIn('id', $songIds)
                ->with(['artist', 'album', 'smartFolder', 'genres', 'tags'])
                ->get();
            $data['songs'] = SongResource::collection($songs);
        }

        if (!$type || $type === 'album') {
            $albumIds = Favorite::where('user_id', $user->id)
                ->where('favoritable_type', Album::class)
                ->pluck('favoritable_id')
                ->map(fn ($id) => (int) $id);
            $albums = Album::whereIn('id', $albumIds)
                ->with('artist')
                ->get();
            $data['albums'] = AlbumResource::collection($albums);
        }

        if (!$type || $type === 'artist') {
            $artistIds = Favorite::where('user_id', $user->id)
                ->where('favoritable_type', Artist::class)
                ->pluck('favoritable_id')
                ->map(fn ($id) => (int) $id);
            $artists = Artist::whereIn('id', $artistIds)->get();
            $data['artists'] = ArtistResource::collection($artists);
        }

        if (!$type || $type === 'playlist') {
            $playlistIds = Favorite::where('user_id', $user->id)
                ->where('favoritable_type', Playlist::class)
                ->pluck('favoritable_id');
            $playlists = Playlist::whereIn('id', $playlistIds)->get();
            $data['playlists'] = PlaylistResource::collection($playlists);
        }

        return response()->json(['data' => $data]);
    }

    /**
     * Add a favorite.
     */
    public function store(Request $request): JsonResponse
    {
        $request->validate([
            'type' => ['required', 'string', 'in:song,album,artist,playlist'],
            'id' => ['required', 'string'],
        ]);

        try {
            $model = $this->resolveFavoritableModel->execute(
                $request->input('type'),
                $request->input('id')
            );
        } catch (ResourceNotFoundException $e) {
            return response()->json([
                'error' => [
                    'code' => $e->getErrorCode(),
                    'message' => $e->getMessage(),
                ],
            ], $e->getStatusCode());
        }

        $this->addFavorite->execute($request->user(), $model);

        return response()->json(['data' => ['favorited' => true]], 201);
    }

    /**
     * Remove a favorite.
     */
    public function destroy(Request $request): JsonResponse
    {
        $request->validate([
            'type' => ['required', 'string', 'in:song,album,artist,playlist'],
            'id' => ['required', 'string'],
        ]);

        try {
            $model = $this->resolveFavoritableModel->execute(
                $request->input('type'),
                $request->input('id')
            );
        } catch (ResourceNotFoundException $e) {
            return response()->json([
                'error' => [
                    'code' => $e->getErrorCode(),
                    'message' => $e->getMessage(),
                ],
            ], $e->getStatusCode());
        }

        $this->removeFavorite->execute($request->user(), $model);

        return response()->json(null, 204);
    }
}

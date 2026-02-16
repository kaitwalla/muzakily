<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\Album;
use App\Models\Artist;
use App\Models\DeletedItem;
use App\Models\Playlist;
use App\Models\Song;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;

class SyncController extends Controller
{
    /**
     * Get deleted items since a given timestamp.
     */
    public function deleted(Request $request): JsonResponse
    {
        $request->validate([
            'since' => ['required', 'date'],
        ]);

        $since = Carbon::parse($request->input('since'));
        $user = $request->user();

        // Get deleted songs (not user-scoped)
        $deletedSongs = DeletedItem::ofType('song')
            ->since($since)
            ->pluck('deletable_id')
            ->toArray();

        // Get deleted albums (not user-scoped)
        $deletedAlbums = DeletedItem::ofType('album')
            ->since($since)
            ->pluck('deletable_id')
            ->toArray();

        // Get deleted artists (not user-scoped)
        $deletedArtists = DeletedItem::ofType('artist')
            ->since($since)
            ->pluck('deletable_id')
            ->toArray();

        // Get deleted playlists (user-scoped)
        $deletedPlaylists = $user
            ? DeletedItem::ofType('playlist')
                ->forUser($user)
                ->since($since)
                ->pluck('deletable_id')
                ->toArray()
            : [];

        return response()->json([
            'data' => [
                'songs' => $deletedSongs,
                'albums' => $deletedAlbums,
                'artists' => $deletedArtists,
                'playlists' => $deletedPlaylists,
            ],
            'meta' => [
                'since' => $since->toIso8601String(),
                'queried_at' => now()->toIso8601String(),
            ],
        ]);
    }

    /**
     * Get sync status with counts and last updated timestamps.
     */
    public function status(Request $request): JsonResponse
    {
        $user = $request->user();

        // Get counts and last updated timestamps
        $songsCount = Song::count();
        $songsLastUpdated = Song::max('updated_at');

        $albumsCount = Album::count();
        $albumsLastUpdated = Album::max('updated_at');

        $artistsCount = Artist::count();
        $artistsLastUpdated = Artist::max('updated_at');

        $playlistsCount = $user?->playlists()->count() ?? 0;
        $playlistsLastUpdated = $user?->playlists()->max('updated_at');

        // Calculate overall library updated_at
        $timestamps = array_filter([
            $songsLastUpdated,
            $albumsLastUpdated,
            $artistsLastUpdated,
            $playlistsLastUpdated,
        ]);

        $libraryUpdatedAt = !empty($timestamps)
            ? Carbon::parse(max($timestamps))->toIso8601String()
            : null;

        return response()->json([
            'data' => [
                'songs' => [
                    'count' => $songsCount,
                    'last_updated' => $songsLastUpdated
                        ? Carbon::parse($songsLastUpdated)->toIso8601String()
                        : null,
                ],
                'albums' => [
                    'count' => $albumsCount,
                    'last_updated' => $albumsLastUpdated
                        ? Carbon::parse($albumsLastUpdated)->toIso8601String()
                        : null,
                ],
                'artists' => [
                    'count' => $artistsCount,
                    'last_updated' => $artistsLastUpdated
                        ? Carbon::parse($artistsLastUpdated)->toIso8601String()
                        : null,
                ],
                'playlists' => [
                    'count' => $playlistsCount,
                    'last_updated' => $playlistsLastUpdated
                        ? Carbon::parse($playlistsLastUpdated)->toIso8601String()
                        : null,
                ],
                'library_updated_at' => $libraryUpdatedAt,
            ],
        ]);
    }
}

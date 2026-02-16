<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\BulkUpdateSongsRequest;
use App\Http\Requests\Api\V1\UpdateSongRequest;
use App\Http\Resources\Api\V1\SongResource;
use App\Models\Interaction;
use App\Models\Song;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

class SongController extends Controller
{
    /**
     * Display a listing of songs.
     */
    public function index(Request $request): AnonymousResourceCollection
    {
        $query = Song::query()
            ->with(['artist', 'album', 'genres', 'tags']);

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

        if ($format = $request->input('format')) {
            $query->where('audio_format', $format);
        }

        if ($request->boolean('favorited') && $request->user()) {
            $query->whereHas('favorites', fn ($q) => $q->where('user_id', $request->user()->id));
        }

        // Filter for incomplete metadata
        if ($request->boolean('incomplete')) {
            $query->where(function ($q) {
                $q->whereNull('album_id')
                    ->orWhereNull('artist_id')
                    ->orWhere('artist_name', '')
                    ->orWhere('artist_name', 'Unknown')
                    ->orWhere('artist_name', 'Unknown Artist');
            });
        }

        // Filter by updated_since for incremental sync
        if ($updatedSince = $request->input('updated_since')) {
            try {
                $query->where('updated_at', '>=', Carbon::parse($updatedSince));
            } catch (\Carbon\Exceptions\InvalidFormatException) {
                abort(422, 'Invalid date format for updated_since parameter');
            }
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
        $song->load(['artist', 'album', 'genres', 'tags']);

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
            'data' => new SongResource($song->fresh(['artist', 'album', 'genres', 'tags'])),
        ]);
    }

    /**
     * Bulk update multiple songs.
     */
    public function bulkUpdate(BulkUpdateSongsRequest $request): JsonResponse
    {
        $songs = Song::whereIn('id', $request->song_ids)->get();

        // Authorize each song
        foreach ($songs as $song) {
            $this->authorize('update', $song);
        }

        /** @var array<string, mixed> $updateData */
        $updateData = $request->safe()->except(['song_ids', 'add_tag_ids', 'remove_tag_ids']);

        /** @var array<int>|null $addTagIds */
        $addTagIds = $request->validated('add_tag_ids');

        /** @var array<int>|null $removeTagIds */
        $removeTagIds = $request->validated('remove_tag_ids');

        DB::transaction(function () use ($songs, $updateData, $addTagIds, $removeTagIds): void {
            foreach ($songs as $song) {
                if (count($updateData) > 0) {
                    $song->update($updateData);
                }
                if ($addTagIds !== null && count($addTagIds) > 0) {
                    $song->tags()->syncWithoutDetaching($addTagIds);
                }
                if ($removeTagIds !== null && count($removeTagIds) > 0) {
                    $song->tags()->detach($removeTagIds);
                }
            }
        });

        return response()->json([
            'data' => SongResource::collection($songs->fresh(['artist', 'album', 'genres', 'tags'])),
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

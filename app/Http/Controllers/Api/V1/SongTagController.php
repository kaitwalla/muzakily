<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Resources\Api\V1\SongResource;
use App\Models\Song;
use App\Services\Library\TagService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class SongTagController extends Controller
{
    public function __construct(
        private TagService $tagService,
    ) {}

    /**
     * Add tags to a song.
     */
    public function store(Request $request, Song $song): JsonResponse
    {
        $this->authorize('tag', $song);

        $request->validate([
            'tag_ids' => ['required', 'array', 'min:1'],
            'tag_ids.*' => ['integer', 'exists:tags,id'],
        ]);

        /** @var array<int, int> $tagIds */
        $tagIds = $request->input('tag_ids');

        $this->tagService->addTagsToSong($song, $tagIds);

        $song->load(['artist', 'album', 'genres', 'tags']);

        return response()->json([
            'data' => new SongResource($song),
        ]);
    }

    /**
     * Remove tags from a song.
     */
    public function destroy(Request $request, Song $song): JsonResponse
    {
        $this->authorize('tag', $song);

        $request->validate([
            'tag_ids' => ['required', 'array', 'min:1'],
            'tag_ids.*' => ['integer', 'exists:tags,id'],
        ]);

        /** @var array<int, int> $tagIds */
        $tagIds = $request->input('tag_ids');

        $this->tagService->removeTagsFromSong($song, $tagIds);

        $song->load(['artist', 'album', 'genres', 'tags']);

        return response()->json([
            'data' => new SongResource($song),
        ]);
    }
}

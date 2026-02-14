<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1;

use App\Actions\Tags\CreateTag;
use App\Actions\Tags\UpdateTag;
use App\Exceptions\CircularTagReferenceException;
use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\CreateTagRequest;
use App\Http\Requests\Api\V1\UpdateTagRequest;
use App\Http\Resources\Api\V1\SongResource;
use App\Http\Resources\Api\V1\TagResource;
use App\Models\Tag;
use App\Services\Library\TagService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class TagController extends Controller
{
    public function __construct(
        private readonly CreateTag $createTag,
        private readonly UpdateTag $updateTag,
        private readonly TagService $tagService,
    ) {}

    /**
     * Display a listing of tags.
     */
    public function index(Request $request): AnonymousResourceCollection
    {
        $flat = $request->boolean('flat', false);

        if ($flat) {
            $tags = $this->tagService->getAvailableTags();
        } else {
            $tags = $this->tagService->getTagsWithHierarchy();
        }

        return TagResource::collection($tags);
    }

    /**
     * Store a newly created tag.
     */
    public function store(CreateTagRequest $request): JsonResponse
    {
        $tag = $this->createTag->execute($request->validated());

        return response()->json([
            'data' => new TagResource($tag),
        ], 201);
    }

    /**
     * Display the specified tag.
     */
    public function show(Tag $tag): JsonResponse
    {
        return response()->json([
            'data' => new TagResource($tag),
        ]);
    }

    /**
     * Update the specified tag.
     */
    public function update(UpdateTagRequest $request, Tag $tag): JsonResponse
    {
        try {
            $tag = $this->updateTag->execute($tag, $request->validated());
        } catch (CircularTagReferenceException $e) {
            abort(422, $e->getMessage());
        }

        return response()->json([
            'data' => new TagResource($tag),
        ]);
    }

    /**
     * Remove the specified tag.
     */
    public function destroy(Tag $tag): JsonResponse
    {
        $this->authorize('delete', $tag);

        $tag->delete();

        return response()->json(null, 204);
    }

    /**
     * Get songs with this tag.
     */
    public function songs(Request $request, Tag $tag): AnonymousResourceCollection
    {
        $includeChildren = $request->boolean('include_children', false);

        if ($includeChildren) {
            $descendantIds = $tag->getDescendantIds();
            $allTagIds = array_merge([$tag->id], $descendantIds);
            $query = \App\Models\Song::whereHas('tags', function ($q) use ($allTagIds) {
                $q->whereIn('tags.id', $allTagIds);
            })->with(['artist', 'album']);
        } else {
            $query = $tag->songs()->with(['artist', 'album']);
        }

        $songs = $query->orderBy('title')->paginate(
            $request->integer('per_page', 50)
        );

        return SongResource::collection($songs);
    }
}

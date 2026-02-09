<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1;

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
        private TagService $tagService,
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
        $parent = null;
        if ($request->filled('parent_id')) {
            $parent = Tag::findOrFail($request->integer('parent_id'));
        }

        $tag = new Tag([
            'name' => $request->string('name')->toString(),
            'color' => $request->string('color')->toString() ?: Tag::getDefaultColor($request->string('name')->toString()),
            'auto_assign_pattern' => $request->string('auto_assign_pattern')->toString() ?: null,
            'depth' => $parent ? $parent->depth + 1 : 1,
        ]);

        if ($parent) {
            $tag->parent_id = $parent->id;
        }

        $tag->save();

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
        $data = [];
        $parentChanged = false;

        if ($request->has('name')) {
            $data['name'] = $request->string('name')->toString();
        }

        if ($request->has('color')) {
            $data['color'] = $request->string('color')->toString();
        }

        if ($request->has('parent_id')) {
            $data['parent_id'] = $request->integer('parent_id') ?: null;

            // Prevent circular references
            if ($data['parent_id']) {
                if ($data['parent_id'] === $tag->id) {
                    abort(422, 'A tag cannot be its own parent.');
                }
                $descendantIds = $tag->getDescendantIds();
                if (in_array($data['parent_id'], $descendantIds, true)) {
                    abort(422, 'A tag cannot have a descendant as its parent.');
                }
            }

            $parent = $data['parent_id'] ? Tag::find($data['parent_id']) : null;
            $data['depth'] = $parent ? $parent->depth + 1 : 1;
            $parentChanged = $tag->parent_id !== $data['parent_id'];
        }

        if ($request->has('auto_assign_pattern')) {
            $data['auto_assign_pattern'] = $request->string('auto_assign_pattern')->toString() ?: null;
        }

        $tag->update($data);

        // Cascade depth updates to descendants if parent changed
        if ($parentChanged) {
            $tag->refresh();
            $tag->updateDescendantDepths();
        }

        return response()->json([
            'data' => new TagResource($tag->fresh()),
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

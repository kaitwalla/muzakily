<?php

declare(strict_types=1);

namespace App\Actions\Tags;

use App\Exceptions\CircularTagReferenceException;
use App\Models\Tag;
use Illuminate\Support\Facades\DB;

final readonly class UpdateTag
{
    /**
     * Update an existing tag.
     *
     * @param array{name?: string, color?: string|null, parent_id?: int|null, auto_assign_pattern?: string|null} $data
     * @throws CircularTagReferenceException
     */
    public function execute(Tag $tag, array $data): Tag
    {
        return DB::transaction(function () use ($tag, $data): Tag {
            $updateData = [];
            $parentChanged = false;

            if (array_key_exists('name', $data)) {
                $updateData['name'] = $data['name'];
            }

            if (array_key_exists('color', $data)) {
                $updateData['color'] = $data['color'];
            }

            if (array_key_exists('parent_id', $data)) {
                $newParentId = $data['parent_id'];
                $parent = null;

                // Validate and prevent circular references
                if ($newParentId !== null) {
                    if ($newParentId === $tag->id) {
                        throw new CircularTagReferenceException('A tag cannot be its own parent.');
                    }

                    $parent = Tag::find($newParentId);
                    if ($parent === null) {
                        throw new \InvalidArgumentException('Parent tag does not exist.');
                    }

                    $descendantIds = $tag->getDescendantIds();
                    if (in_array($newParentId, $descendantIds, true)) {
                        throw new CircularTagReferenceException('A tag cannot have a descendant as its parent.');
                    }
                }

                $updateData['parent_id'] = $newParentId;
                $updateData['depth'] = $parent ? $parent->depth + 1 : 1;
                $parentChanged = $tag->parent_id !== $newParentId;
            }

            if (array_key_exists('auto_assign_pattern', $data)) {
                $updateData['auto_assign_pattern'] = $data['auto_assign_pattern'] ?: null;
            }

            $tag->update($updateData);

            // Cascade depth updates to descendants if parent changed
            if ($parentChanged) {
                $tag->refresh();
                $tag->updateDescendantDepths();
            }

            return $tag->refresh();
        });
    }
}

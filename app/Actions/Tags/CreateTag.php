<?php

declare(strict_types=1);

namespace App\Actions\Tags;

use App\Models\Tag;

final readonly class CreateTag
{
    /**
     * Create a new tag.
     *
     * @param array{name: string, color?: string|null, parent_id?: int|null, auto_assign_pattern?: string|null} $data
     */
    public function execute(array $data): Tag
    {
        $parent = null;
        if (!empty($data['parent_id'])) {
            $parent = Tag::findOrFail($data['parent_id']);
        }

        $tag = new Tag([
            'name' => $data['name'],
            'color' => ($data['color'] ?? null) ?: Tag::getDefaultColor($data['name']),
            'auto_assign_pattern' => ($data['auto_assign_pattern'] ?? null) ?: null,
            'depth' => $parent ? $parent->depth + 1 : 1,
        ]);

        if ($parent) {
            $tag->parent_id = $parent->id;
        }

        $tag->save();

        return $tag;
    }
}

<?php

declare(strict_types=1);

namespace App\Actions\Tags;

use App\Models\Tag;
use Illuminate\Database\Eloquent\Collection;

final readonly class CreateTagsFromPath
{
    /**
     * Find or create tags from a storage path.
     *
     * @param string $path The filesystem path to parse into tags
     * @param list<string> $specialFolders Folders that get special handling (e.g., 'xmas')
     * @return Collection<int, Tag>
     */
    public function execute(string $path, array $specialFolders = []): Collection
    {
        return Tag::findOrCreateTagsFromPath($path, $specialFolders);
    }
}

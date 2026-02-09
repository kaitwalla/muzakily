<?php

declare(strict_types=1);

namespace App\Services\Library;

use App\Models\Song;
use App\Models\Tag;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\DB;

class TagService
{
    /**
     * Get the list of special folders from config.
     *
     * @return list<string>
     */
    public function getSpecialFolders(): array
    {
        /** @var list<string> $folders */
        $folders = config('muzakily.tags.special_folders', ['Xmas', 'Holiday', 'Seasonal']);

        return $folders;
    }

    /**
     * Extract tag name from a storage path.
     */
    public function extractTagNameFromPath(string $path): ?string
    {
        return Tag::extractFromPath($path, $this->getSpecialFolders());
    }

    /**
     * Assign a tag to a song based on its storage path.
     */
    public function assignFromPath(Song $song): ?Tag
    {
        $tag = Tag::findOrCreateFromPath($song->storage_path, $this->getSpecialFolders());

        if ($tag === null) {
            return null;
        }

        // Attach tag if not already attached (atomic operation)
        $song->tags()->syncWithoutDetaching([$tag->id => ['auto_assigned' => true]]);

        return $tag;
    }

    /**
     * Find or create a tag by name.
     */
    public function findOrCreate(string $name, ?Tag $parent = null): Tag
    {
        // Check if tag with this name exists first
        $existingTag = Tag::where('name', $name)->first();
        if ($existingTag) {
            return $existingTag;
        }

        // Generate slug only when creating
        $slug = Tag::generateUniqueSlug($name);

        return DB::transaction(function () use ($name, $slug, $parent): Tag {
            // Double-check inside transaction to prevent race condition
            $existingTag = Tag::where('name', $name)->lockForUpdate()->first();
            if ($existingTag) {
                return $existingTag;
            }

            $tag = new Tag([
                'name' => $name,
                'slug' => $slug,
                'depth' => $parent ? $parent->depth + 1 : 1,
                'color' => Tag::getDefaultColor($name),
            ]);

            if ($parent) {
                $tag->parent_id = $parent->id;
            }

            $tag->save();

            return $tag;
        });
    }

    /**
     * Sync tags for a song.
     *
     * @param list<int> $tagIds
     */
    public function syncSongTags(Song $song, array $tagIds, bool $autoAssigned = false): void
    {
        $syncData = [];
        foreach ($tagIds as $tagId) {
            $syncData[$tagId] = ['auto_assigned' => $autoAssigned];
        }

        $song->tags()->sync($syncData);
    }

    /**
     * Get all available tags.
     *
     * @return Collection<int, Tag>
     */
    public function getAvailableTags(): Collection
    {
        return Tag::query()
            ->orderBy('name')
            ->get();
    }

    /**
     * Get root-level tags with their children.
     *
     * @return Collection<int, Tag>
     */
    public function getTagsWithHierarchy(): Collection
    {
        return Tag::roots()
            ->with(['children' => function ($query) {
                $query->orderBy('name');
            }])
            ->orderBy('name')
            ->get();
    }

    /**
     * Merge one tag into another, moving all songs.
     */
    public function mergeTags(Tag $source, Tag $target): void
    {
        DB::transaction(function () use ($source, $target): void {
            // Get all song IDs from source
            $songIds = $source->songs()->pluck('songs.id')->toArray();

            // Attach to target (ignoring duplicates) - bulk operation
            $syncData = array_fill_keys($songIds, ['auto_assigned' => false]);
            $target->songs()->syncWithoutDetaching($syncData);

            // Delete source tag
            $source->delete();

            // Update target song count
            $target->updateSongCount();
        });
    }

    /**
     * Add tags to a song.
     *
     * @param list<int> $tagIds
     */
    public function addTagsToSong(Song $song, array $tagIds): void
    {
        foreach ($tagIds as $tagId) {
            if (!$song->tags()->where('tag_id', $tagId)->exists()) {
                $song->tags()->attach($tagId, ['auto_assigned' => false]);
            }
        }
    }

    /**
     * Remove tags from a song.
     *
     * @param list<int> $tagIds
     */
    public function removeTagsFromSong(Song $song, array $tagIds): void
    {
        $song->tags()->detach($tagIds);
    }

    /**
     * Update song counts for all tags.
     */
    public function updateAllSongCounts(): void
    {
        Tag::all()->each->updateSongCount();
    }
}

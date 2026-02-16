<?php

declare(strict_types=1);

namespace App\Services\Library;

use App\Jobs\UpdateSmartPlaylistsForSongJob;
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
     * Extract tag names from a storage path.
     *
     * @return list<string>
     */
    public function extractTagNamesFromPath(string $path): array
    {
        return Tag::extractTagNamesFromPath($path, $this->getSpecialFolders());
    }

    /**
     * Assign tags to a song based on its storage path.
     *
     * @return Collection<int, Tag>
     */
    public function assignFromPath(Song $song): Collection
    {
        $tags = Tag::findOrCreateTagsFromPath($song->storage_path, $this->getSpecialFolders());

        if ($tags->isEmpty()) {
            return $tags;
        }

        // Attach all tags if not already attached (atomic operation)
        $syncData = [];
        foreach ($tags as $tag) {
            $syncData[$tag->id] = ['auto_assigned' => true];
        }
        $song->tags()->syncWithoutDetaching($syncData);

        return $tags;
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

        // Dispatch job to update smart playlists with tag rules
        UpdateSmartPlaylistsForSongJob::dispatch($song);
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
        $changed = false;
        foreach ($tagIds as $tagId) {
            if (!$song->tags()->where('tag_id', $tagId)->exists()) {
                $song->tags()->attach($tagId, ['auto_assigned' => false]);
                $changed = true;
            }
        }

        // Dispatch job to update smart playlists with tag rules
        if ($changed) {
            UpdateSmartPlaylistsForSongJob::dispatch($song);
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

        // Dispatch job to update smart playlists with tag rules
        if (count($tagIds) > 0) {
            UpdateSmartPlaylistsForSongJob::dispatch($song);
        }
    }

    /**
     * Update song counts for all tags.
     */
    public function updateAllSongCounts(): void
    {
        Tag::all()->each->updateSongCount();
    }
}

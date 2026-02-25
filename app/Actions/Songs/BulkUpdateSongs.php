<?php

declare(strict_types=1);

namespace App\Actions\Songs;

use App\Models\Song;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\DB;

final readonly class BulkUpdateSongs
{
    /**
     * Bulk update multiple songs with optional tag modifications.
     *
     * @param Collection<int, Song> $songs
     * @param array<string, mixed> $updateData
     * @param array<int>|null $addTagIds
     * @param array<int>|null $removeTagIds
     * @return Collection<int, Song>
     */
    public function execute(
        Collection $songs,
        array $updateData,
        ?array $addTagIds,
        ?array $removeTagIds
    ): Collection {
        if ($songs->isEmpty()) {
            return $songs;
        }

        // Filter out overlapping IDs - add takes precedence over remove
        $effectiveRemoveTagIds = $removeTagIds;
        if ($addTagIds !== null && $removeTagIds !== null) {
            $effectiveRemoveTagIds = array_values(array_diff($removeTagIds, $addTagIds));
        }

        DB::transaction(function () use ($songs, $updateData, $addTagIds, $effectiveRemoveTagIds): void {
            foreach ($songs as $song) {
                if (count($updateData) > 0) {
                    $song->update($updateData);
                }
                if ($addTagIds !== null && count($addTagIds) > 0) {
                    $song->tags()->syncWithoutDetaching($addTagIds);
                }
                if ($effectiveRemoveTagIds !== null && count($effectiveRemoveTagIds) > 0) {
                    $song->tags()->detach($effectiveRemoveTagIds);
                }
            }
        });

        return $songs->fresh(['artist', 'album', 'genres', 'tags']);
    }
}

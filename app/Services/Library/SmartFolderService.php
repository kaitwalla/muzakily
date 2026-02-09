<?php

declare(strict_types=1);

namespace App\Services\Library;

use App\Models\SmartFolder;
use App\Models\Song;

class SmartFolderService
{
    /**
     * Get the list of special folders from config.
     *
     * @return array<string>
     */
    private function getSpecialFolders(): array
    {
        return config('muzakily.smart_folders.special', []);
    }

    /**
     * Assign a smart folder to a song based on its path.
     */
    public function assignFromPath(string $storagePath): ?SmartFolder
    {
        $pathPrefix = SmartFolder::extractFromPath($storagePath, $this->getSpecialFolders());

        if ($pathPrefix === null) {
            return null;
        }

        return $this->findOrCreate($pathPrefix);
    }

    /**
     * Find or create a smart folder.
     */
    public function findOrCreate(string $pathPrefix): SmartFolder
    {
        return SmartFolder::findOrCreateFromPath($pathPrefix, $this->getSpecialFolders());
    }

    /**
     * Get all available folders for filtering.
     *
     * @return \Illuminate\Database\Eloquent\Collection<int, SmartFolder>
     */
    public function getAvailableFolders(): \Illuminate\Database\Eloquent\Collection
    {
        return SmartFolder::getAvailableFolders();
    }

    /**
     * Reassign all songs to their smart folders.
     * Useful when folder configuration changes.
     */
    public function reassignAll(): int
    {
        $count = 0;

        Song::cursor()->each(function (Song $song) use (&$count) {
            $folder = $this->assignFromPath($song->storage_path);

            if ($folder && $song->smart_folder_id !== $folder->id) {
                $song->update(['smart_folder_id' => $folder->id]);
                $count++;
            }
        });

        // Update song counts
        SmartFolder::all()->each->updateSongCount();

        return $count;
    }
}

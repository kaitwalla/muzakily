<?php

declare(strict_types=1);

namespace App\Actions\Playlists;

use App\Models\Playlist;
use App\Models\User;

final readonly class ReorderPlaylists
{
    /**
     * Reorder playlists for a user.
     *
     * Only playlists owned by the user are reordered. IDs that don't exist
     * or belong to other users are ignored, and positions are assigned
     * contiguously starting from 0.
     *
     * @param array<string> $playlistIds
     */
    public function execute(User $user, array $playlistIds): void
    {
        // Get valid playlist IDs that belong to the user
        $validIds = Playlist::where('user_id', $user->id)
            ->whereIn('id', $playlistIds)
            ->pluck('id')
            ->toArray();

        // Build a position map for valid IDs based on their order in the input
        $positionMap = [];
        $position = 0;
        foreach ($playlistIds as $playlistId) {
            if (in_array($playlistId, $validIds, true)) {
                $positionMap[$playlistId] = $position++;
            }
        }

        // Update positions
        foreach ($positionMap as $playlistId => $newPosition) {
            Playlist::where('id', $playlistId)
                ->where('user_id', $user->id)
                ->update(['position' => $newPosition]);
        }
    }
}

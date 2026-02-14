<?php

declare(strict_types=1);

namespace App\Observers;

use App\Jobs\RefreshSmartPlaylistJob;
use App\Models\Playlist;

class PlaylistObserver
{
    /**
     * Handle the Playlist "created" event.
     */
    public function created(Playlist $playlist): void
    {
        if ($playlist->is_smart) {
            RefreshSmartPlaylistJob::dispatch($playlist);
        }
    }

    /**
     * Handle the Playlist "updated" event.
     */
    public function updated(Playlist $playlist): void
    {
        // Only refresh if rules changed
        if ($playlist->is_smart && $playlist->wasChanged('rules')) {
            RefreshSmartPlaylistJob::dispatch($playlist);
        }
    }
}

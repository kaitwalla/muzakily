<?php

declare(strict_types=1);

namespace App\Observers;

use App\Jobs\UpdateSmartPlaylistsForSongJob;
use App\Models\Song;

class SongObserver
{
    /**
     * Handle the Song "created" event.
     */
    public function created(Song $song): void
    {
        UpdateSmartPlaylistsForSongJob::dispatch($song);
    }

    /**
     * Handle the Song "updated" event.
     */
    public function updated(Song $song): void
    {
        // Only dispatch if relevant fields changed that could affect smart playlist matching
        $relevantFields = [
            'title',
            'artist_name',
            'album_name',
            'year',
            'length',
            'audio_format',
        ];

        if ($song->wasChanged($relevantFields)) {
            UpdateSmartPlaylistsForSongJob::dispatch($song);
        }
    }

    /**
     * Handle the Song "deleted" event (soft delete).
     */
    public function deleted(Song $song): void
    {
        // Remove soft-deleted songs from smart playlists
        UpdateSmartPlaylistsForSongJob::dispatch($song, removing: true);
    }

    /**
     * Handle the Song "restored" event.
     */
    public function restored(Song $song): void
    {
        UpdateSmartPlaylistsForSongJob::dispatch($song);
    }

    /**
     * Handle the Song "force deleted" event.
     */
    public function forceDeleted(Song $song): void
    {
        // Song will be removed from all playlists via cascade,
        // but dispatch for any cleanup needed
        UpdateSmartPlaylistsForSongJob::dispatch($song, removing: true);
    }
}

<?php

declare(strict_types=1);

namespace App\Observers;

use App\Jobs\UpdateSmartPlaylistsForInteractionJob;
use App\Models\Interaction;

class InteractionObserver
{
    /**
     * Handle the Interaction "created" event.
     */
    public function created(Interaction $interaction): void
    {
        UpdateSmartPlaylistsForInteractionJob::dispatch($interaction);
    }

    /**
     * Handle the Interaction "updated" event.
     */
    public function updated(Interaction $interaction): void
    {
        // Only dispatch if relevant fields changed
        if ($interaction->wasChanged(['play_count', 'last_played_at'])) {
            UpdateSmartPlaylistsForInteractionJob::dispatch($interaction);
        }
    }
}

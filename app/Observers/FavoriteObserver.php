<?php

declare(strict_types=1);

namespace App\Observers;

use App\Jobs\UpdateSmartPlaylistsForFavoriteJob;
use App\Models\Favorite;

class FavoriteObserver
{
    /**
     * Handle the Favorite "created" event.
     */
    public function created(Favorite $favorite): void
    {
        if ($favorite->favoritable_type === 'song') {
            UpdateSmartPlaylistsForFavoriteJob::dispatch(
                $favorite->user,
                $favorite->favoritable_id
            );
        }
    }

    /**
     * Handle the Favorite "deleted" event.
     */
    public function deleted(Favorite $favorite): void
    {
        if ($favorite->favoritable_type === 'song') {
            UpdateSmartPlaylistsForFavoriteJob::dispatch(
                $favorite->user,
                $favorite->favoritable_id
            );
        }
    }
}

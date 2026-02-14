<?php

declare(strict_types=1);

namespace App\Actions\Favorites;

use App\Models\Album;
use App\Models\Artist;
use App\Models\Favorite;
use App\Models\Playlist;
use App\Models\Song;
use App\Models\User;

final readonly class RemoveFavorite
{
    /**
     * Remove a model from user's favorites.
     */
    public function execute(User $user, Song|Album|Artist|Playlist $model): void
    {
        Favorite::remove($user, $model);
    }
}

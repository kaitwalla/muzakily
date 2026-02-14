<?php

declare(strict_types=1);

namespace App\Actions\Favorites;

use App\Models\Album;
use App\Models\Artist;
use App\Models\Favorite;
use App\Models\Playlist;
use App\Models\Song;
use App\Models\User;

final readonly class AddFavorite
{
    /**
     * Add a model to user's favorites.
     */
    public function execute(User $user, Song|Album|Artist|Playlist $model): void
    {
        Favorite::add($user, $model);
    }
}

<?php

declare(strict_types=1);

namespace App\Policies;

use App\Models\Playlist;
use App\Models\User;

class PlaylistPolicy
{
    /**
     * Determine whether the user can view the playlist.
     */
    public function view(User $user, Playlist $playlist): bool
    {
        return $user->id === $playlist->user_id || $user->isAdmin();
    }

    /**
     * Determine whether the user can update the playlist.
     */
    public function update(User $user, Playlist $playlist): bool
    {
        return $user->id === $playlist->user_id || $user->isAdmin();
    }

    /**
     * Determine whether the user can delete the playlist.
     */
    public function delete(User $user, Playlist $playlist): bool
    {
        return $user->id === $playlist->user_id || $user->isAdmin();
    }
}

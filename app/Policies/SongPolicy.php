<?php

declare(strict_types=1);

namespace App\Policies;

use App\Models\Song;
use App\Models\User;

class SongPolicy
{
    /**
     * Determine whether the user can update the song.
     */
    public function update(User $user, Song $song): bool
    {
        return $user->isAdmin();
    }

    /**
     * Determine whether the user can upload songs.
     */
    public function upload(User $user): bool
    {
        return $user->isAdmin();
    }
}

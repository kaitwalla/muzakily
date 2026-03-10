<?php

declare(strict_types=1);

namespace App\Actions\Interactions;

use App\Models\Interaction;
use App\Models\Song;
use App\Models\User;

final readonly class RecordSongPlay
{
    /**
     * Record a play for a song by a user.
     */
    public function execute(User $user, Song $song): Interaction
    {
        return Interaction::recordPlay($user, $song);
    }
}

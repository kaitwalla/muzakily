<?php

declare(strict_types=1);

namespace App\Actions\Player;

use App\Events\Player\QueueUpdated;
use App\Models\PlayerDevice;
use App\Models\User;

final readonly class SyncDeviceQueue
{
    /**
     * Sync queue across all user's devices.
     *
     * @param array<string> $queue
     * @return int Number of recently active devices
     */
    public function execute(User $user, array $queue, int $currentIndex, float $position): int
    {
        broadcast(new QueueUpdated($user, $queue, $currentIndex, $position));

        return PlayerDevice::where('user_id', $user->id)
            ->where('last_seen', '>=', now()->subMinutes(1))
            ->count();
    }
}

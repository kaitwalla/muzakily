<?php

declare(strict_types=1);

namespace App\Actions\Player;

use App\Events\Player\RemoteCommand;
use App\Exceptions\DeviceNotFoundException;
use App\Models\PlayerDevice;
use App\Models\User;

final readonly class SendRemoteCommand
{
    /**
     * Send a remote control command to a device.
     *
     * @param array<string, mixed> $payload
     * @throws DeviceNotFoundException
     */
    public function execute(User $user, string $targetDeviceId, string $command, array $payload = []): PlayerDevice
    {
        $device = PlayerDevice::where('id', $targetDeviceId)
            ->where('user_id', $user->id)
            ->first();

        if (!$device) {
            throw new DeviceNotFoundException('Target device not found');
        }

        broadcast(new RemoteCommand($user, $targetDeviceId, $command, $payload));

        return $device;
    }
}

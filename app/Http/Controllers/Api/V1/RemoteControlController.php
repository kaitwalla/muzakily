<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1;

use App\Actions\Player\GetPlaybackState;
use App\Actions\Player\SendRemoteCommand;
use App\Actions\Player\SyncDeviceQueue;
use App\Exceptions\DeviceNotFoundException;
use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class RemoteControlController extends Controller
{
    public function __construct(
        private readonly SendRemoteCommand $sendRemoteCommand,
        private readonly GetPlaybackState $getPlaybackState,
        private readonly SyncDeviceQueue $syncDeviceQueue,
    ) {}
    /**
     * Send a remote control command to a device.
     */
    public function control(Request $request): JsonResponse
    {
        $request->validate([
            'target_device_id' => ['required', 'string'],
            'command' => ['required', 'string', 'in:play,pause,stop,next,prev,seek,volume,queue_add,queue_clear'],
            'payload' => ['nullable', 'array'],
        ]);

        $targetDeviceId = $request->input('target_device_id');
        $command = $request->input('command');
        $payload = $request->input('payload', []);

        try {
            $this->sendRemoteCommand->execute(
                $request->user(),
                $targetDeviceId,
                $command,
                $payload
            );
        } catch (DeviceNotFoundException) {
            return response()->json([
                'error' => [
                    'code' => 'NOT_FOUND',
                    'message' => 'The specified device was not found.',
                ],
            ], 404);
        }

        return response()->json([
            'data' => [
                'status' => 'command_sent',
                'target_device_id' => $targetDeviceId,
                'command' => $command,
            ],
        ]);
    }

    /**
     * Get the current playback state.
     */
    public function state(Request $request): JsonResponse
    {
        $state = $this->getPlaybackState->execute($request->user());

        return response()->json([
            'data' => $state,
        ]);
    }

    /**
     * Sync queue across all devices.
     */
    public function sync(Request $request): JsonResponse
    {
        $request->validate([
            'queue' => ['required', 'array'],
            'queue.*' => ['uuid'],
            'current_index' => ['nullable', 'integer', 'min:0'],
            'position' => ['nullable', 'numeric', 'min:0'],
        ]);

        $devicesNotified = $this->syncDeviceQueue->execute(
            $request->user(),
            $request->input('queue'),
            (int) $request->input('current_index', 0),
            (float) $request->input('position', 0)
        );

        return response()->json([
            'data' => [
                'status' => 'synced',
                'devices_notified' => $devicesNotified,
            ],
        ]);
    }
}

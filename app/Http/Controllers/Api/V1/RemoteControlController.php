<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1;

use App\Events\Player\RemoteCommand;
use App\Events\Player\QueueUpdated;
use App\Http\Controllers\Controller;
use App\Http\Resources\Api\V1\SongResource;
use App\Models\PlayerDevice;
use App\Models\Song;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class RemoteControlController extends Controller
{
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

        $user = $request->user();
        $targetDeviceId = $request->input('target_device_id');
        $command = $request->input('command');
        $payload = $request->input('payload', []);

        // Verify the target device belongs to the user
        $device = PlayerDevice::where('id', $targetDeviceId)
            ->where('user_id', $user->id)
            ->first();

        if (!$device) {
            return response()->json([
                'error' => [
                    'code' => 'NOT_FOUND',
                    'message' => 'Target device not found',
                ],
            ], 404);
        }

        // Broadcast the command
        broadcast(new RemoteCommand($user, $targetDeviceId, $command, $payload));

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
        $user = $request->user();

        // Get the most recently active device
        $activeDevice = PlayerDevice::where('user_id', $user->id)
            ->orderBy('last_seen', 'desc')
            ->first();

        if (!$activeDevice) {
            return response()->json([
                'data' => [
                    'active_device' => null,
                    'is_playing' => false,
                    'current_song' => null,
                    'position' => 0,
                    'volume' => 1,
                    'queue' => [],
                ],
            ]);
        }

        $currentSong = $activeDevice->current_song_id
            ? Song::with(['artist', 'album'])->find($activeDevice->current_song_id)
            : null;

        /** @var array<int, string> $queue */
        $queue = $activeDevice->state['queue'] ?? [];
        $queueSongsMap = Song::whereIn('id', $queue)->get()->keyBy('id');
        // Preserve queue order
        $queueSongs = collect($queue)->map(fn (string $id) => $queueSongsMap->get($id))->filter()->values();

        return response()->json([
            'data' => [
                'active_device' => [
                    'device_id' => $activeDevice->id,
                    'name' => $activeDevice->name,
                ],
                'is_playing' => $activeDevice->is_playing,
                'current_song' => $currentSong ? new SongResource($currentSong) : null,
                'position' => $activeDevice->position ?? 0,
                'volume' => $activeDevice->volume ?? 1,
                'queue' => SongResource::collection($queueSongs),
            ],
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

        $user = $request->user();
        $queue = $request->input('queue');
        $currentIndex = $request->input('current_index', 0);
        $position = $request->input('position', 0);

        // Broadcast queue update to all user's devices
        broadcast(new QueueUpdated($user, $queue, $currentIndex, $position));

        // Count online devices
        $devicesNotified = PlayerDevice::where('user_id', $user->id)
            ->where('last_seen', '>=', now()->subMinutes(1))
            ->count();

        return response()->json([
            'data' => [
                'status' => 'synced',
                'devices_notified' => $devicesNotified,
            ],
        ]);
    }
}

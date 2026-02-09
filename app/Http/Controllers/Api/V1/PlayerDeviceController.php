<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Resources\Api\V1\SongResource;
use App\Models\PlayerDevice;
use App\Models\Song;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class PlayerDeviceController extends Controller
{
    /**
     * Display a listing of the user's player devices.
     */
    public function index(Request $request): JsonResponse
    {
        $devices = PlayerDevice::where('user_id', $request->user()->id)
            ->orderBy('last_seen', 'desc')
            ->get();

        // Batch load songs to avoid N+1 query
        $songIds = $devices->pluck('current_song_id')->filter()->unique()->values();
        $songs = Song::with(['artist', 'album'])->whereIn('id', $songIds)->get()->keyBy('id');

        $data = $devices->map(function (PlayerDevice $device) use ($songs) {
            $currentSong = $device->current_song_id
                ? $songs->get($device->current_song_id)
                : null;

            return [
                'device_id' => $device->id,
                'name' => $device->name,
                'type' => $device->type,
                'is_playing' => $device->is_playing,
                'current_song' => $currentSong ? new SongResource($currentSong) : null,
                'position' => $device->position,
                'volume' => $device->volume,
                'last_seen' => $device->last_seen->toIso8601String(),
            ];
        });

        return response()->json(['data' => $data]);
    }

    /**
     * Register a new player device.
     */
    public function store(Request $request): JsonResponse
    {
        $request->validate([
            'device_id' => ['required', 'string', 'max:64'],
            'name' => ['required', 'string', 'max:255'],
            'type' => ['required', 'string', 'in:web,mobile,desktop'],
        ]);

        $device = PlayerDevice::updateOrCreate(
            [
                'id' => $request->input('device_id'),
                'user_id' => $request->user()->id,
            ],
            [
                'name' => $request->input('name'),
                'type' => $request->input('type'),
                'last_seen' => now(),
            ]
        );

        return response()->json([
            'data' => [
                'device_id' => $device->id,
                'name' => $device->name,
                'type' => $device->type,
                'is_playing' => $device->is_playing,
                'created_at' => $device->created_at->toIso8601String(),
            ],
        ], 201);
    }

    /**
     * Remove the specified player device.
     */
    public function destroy(Request $request, string $device): JsonResponse
    {
        $playerDevice = PlayerDevice::where('id', $device)
            ->where('user_id', $request->user()->id)
            ->first();

        if (!$playerDevice) {
            return response()->json([
                'error' => [
                    'code' => 'NOT_FOUND',
                    'message' => 'Device not found',
                ],
            ], 404);
        }

        $playerDevice->delete();

        return response()->json(null, 204);
    }
}

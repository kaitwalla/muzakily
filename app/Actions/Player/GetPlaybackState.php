<?php

declare(strict_types=1);

namespace App\Actions\Player;

use App\Http\Resources\Api\V1\SongResource;
use App\Models\PlayerDevice;
use App\Models\Song;
use App\Models\User;

final readonly class GetPlaybackState
{
    /**
     * Get the current playback state for a user.
     *
     * @return array{
     *     active_device: array{device_id: string, name: string}|null,
     *     is_playing: bool,
     *     current_song: SongResource|null,
     *     position: float,
     *     volume: float,
     *     queue: \Illuminate\Http\Resources\Json\AnonymousResourceCollection
     * }
     */
    public function execute(User $user): array
    {
        $activeDevice = PlayerDevice::where('user_id', $user->id)
            ->orderBy('last_seen', 'desc')
            ->first();

        if (!$activeDevice) {
            return [
                'active_device' => null,
                'is_playing' => false,
                'current_song' => null,
                'position' => 0,
                'volume' => 1,
                'queue' => SongResource::collection(collect()),
            ];
        }

        $currentSong = $activeDevice->current_song_id
            ? Song::with(['artist', 'album'])->find($activeDevice->current_song_id)
            : null;

        /** @var array<int, int|string> $queue */
        $queue = array_map('strval', $activeDevice->state['queue'] ?? []);
        $queueSongsMap = Song::with(['artist', 'album'])->whereIn('id', $queue)->get()->keyBy('id');
        // Preserve queue order
        $queueSongs = collect($queue)->map(fn (string $id) => $queueSongsMap->get($id))->filter()->values();

        return [
            'active_device' => [
                'device_id' => $activeDevice->id,
                'name' => $activeDevice->name,
            ],
            'is_playing' => $activeDevice->is_playing,
            'current_song' => $currentSong ? new SongResource($currentSong) : null,
            'position' => $activeDevice->position ?? 0,
            'volume' => $activeDevice->volume ?? 1,
            'queue' => SongResource::collection($queueSongs),
        ];
    }
}

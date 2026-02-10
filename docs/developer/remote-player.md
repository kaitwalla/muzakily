# Remote Player

Real-time remote player control using Pusher for device synchronization.

## Overview

The remote player allows:

- Controlling playback on any registered device
- Syncing queues across devices
- Transferring playback between devices
- Real-time status updates

## Architecture

```
┌─────────┐     ┌─────────┐     ┌─────────┐
│ Device  │     │ Device  │     │ Device  │
│    A    │     │    B    │     │    C    │
└────┬────┘     └────┬────┘     └────┬────┘
     │               │               │
     └───────────────┼───────────────┘
                     │
              ┌──────▼──────┐
              │   Pusher    │
              │  Channels   │
              └──────┬──────┘
                     │
              ┌──────▼──────┐
              │   Laravel   │
              │     API     │
              └─────────────┘
```

## Pusher Setup

### Configuration

```env
BROADCAST_DRIVER=pusher
PUSHER_APP_ID=your-app-id
PUSHER_APP_KEY=your-app-key
PUSHER_APP_SECRET=your-app-secret
PUSHER_APP_CLUSTER=mt1
```

### Broadcasting Configuration

```php
// config/broadcasting.php
'connections' => [
    'pusher' => [
        'driver' => 'pusher',
        'key' => env('PUSHER_APP_KEY'),
        'secret' => env('PUSHER_APP_SECRET'),
        'app_id' => env('PUSHER_APP_ID'),
        'options' => [
            'cluster' => env('PUSHER_APP_CLUSTER'),
            'encrypted' => true,
        ],
    ],
],
```

## Data Model

### PlayerDevice Model

```php
class PlayerDevice extends Model
{
    protected $fillable = [
        'device_id',
        'user_id',
        'name',
        'type',
        'is_playing',
        'current_song_id',
        'position',
        'volume',
        'last_seen_at',
    ];

    protected $casts = [
        'is_playing' => 'boolean',
        'position' => 'float',
        'volume' => 'float',
        'last_seen_at' => 'datetime',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function currentSong(): BelongsTo
    {
        return $this->belongsTo(Song::class, 'current_song_id');
    }
}
```

### Database Schema

```php
Schema::create('player_devices', function (Blueprint $table) {
    $table->id();
    $table->string('device_id', 64)->unique();
    $table->foreignId('user_id')->constrained()->cascadeOnDelete();
    $table->string('name');
    $table->enum('type', ['web', 'mobile', 'desktop']);
    $table->boolean('is_playing')->default(false);
    $table->foreignUuid('current_song_id')->nullable()->constrained('songs')->nullOnDelete();
    $table->float('position')->default(0);
    $table->float('volume')->default(1);
    $table->timestamp('last_seen_at')->nullable();
    $table->timestamps();

    $table->index(['user_id', 'device_id']);
});
```

## Events

### PlayerCommandEvent

```php
namespace App\Events;

class PlayerCommandEvent implements ShouldBroadcast
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public function __construct(
        public User $user,
        public string $targetDeviceId,
        public string $command,
        public ?array $payload = null
    ) {}

    public function broadcastOn(): array
    {
        return [
            new PrivateChannel("player.{$this->user->id}"),
        ];
    }

    public function broadcastAs(): string
    {
        return 'player.command';
    }

    public function broadcastWith(): array
    {
        return [
            'target_device_id' => $this->targetDeviceId,
            'command' => $this->command,
            'payload' => $this->payload,
        ];
    }
}
```

### PlayerStateUpdatedEvent

```php
class PlayerStateUpdatedEvent implements ShouldBroadcast
{
    public function __construct(
        public User $user,
        public PlayerDevice $device
    ) {}

    public function broadcastOn(): array
    {
        return [
            new PrivateChannel("player.{$this->user->id}"),
        ];
    }

    public function broadcastAs(): string
    {
        return 'player.state';
    }

    public function broadcastWith(): array
    {
        return [
            'device_id' => $this->device->device_id,
            'is_playing' => $this->device->is_playing,
            'current_song' => $this->device->currentSong
                ? new SongResource($this->device->currentSong)
                : null,
            'position' => $this->device->position,
            'volume' => $this->device->volume,
        ];
    }
}
```

## API Controllers

### PlayerDeviceController

```php
class PlayerDeviceController extends Controller
{
    public function index(): AnonymousResourceCollection
    {
        $devices = auth()->user()->playerDevices()
            ->where('last_seen_at', '>', now()->subMinutes(30))
            ->get();

        return PlayerDeviceResource::collection($devices);
    }

    public function store(RegisterDeviceRequest $request): PlayerDeviceResource
    {
        $device = auth()->user()->playerDevices()->updateOrCreate(
            ['device_id' => $request->input('device_id')],
            [
                'name' => $request->input('name'),
                'type' => $request->input('type'),
                'last_seen_at' => now(),
            ]
        );

        return new PlayerDeviceResource($device);
    }

    public function destroy(string $deviceId): Response
    {
        auth()->user()->playerDevices()
            ->where('device_id', $deviceId)
            ->delete();

        return response()->noContent();
    }
}
```

### PlayerControlController

```php
class PlayerControlController extends Controller
{
    public function control(ControlRequest $request): JsonResponse
    {
        $validated = $request->validated();

        // Verify target device belongs to user
        $device = auth()->user()->playerDevices()
            ->where('device_id', $validated['target_device_id'])
            ->firstOrFail();

        // Broadcast command
        broadcast(new PlayerCommandEvent(
            auth()->user(),
            $validated['target_device_id'],
            $validated['command'],
            $validated['payload'] ?? null
        ))->toOthers();

        return response()->json([
            'data' => [
                'status' => 'command_sent',
                'target_device_id' => $validated['target_device_id'],
                'command' => $validated['command'],
            ],
        ]);
    }

    public function state(): JsonResponse
    {
        $activeDevice = auth()->user()->playerDevices()
            ->where('is_playing', true)
            ->where('last_seen_at', '>', now()->subMinutes(5))
            ->first();

        return response()->json([
            'data' => [
                'active_device' => $activeDevice
                    ? ['device_id' => $activeDevice->device_id, 'name' => $activeDevice->name]
                    : null,
                'is_playing' => $activeDevice?->is_playing ?? false,
                'current_song' => $activeDevice?->currentSong
                    ? new SongResource($activeDevice->currentSong)
                    : null,
                'position' => $activeDevice?->position ?? 0,
                'volume' => $activeDevice?->volume ?? 1,
            ],
        ]);
    }

    public function sync(SyncRequest $request): JsonResponse
    {
        $user = auth()->user();

        // Broadcast queue sync to all devices
        broadcast(new QueueSyncEvent(
            $user,
            $request->input('queue'),
            $request->input('current_index'),
            $request->input('position')
        ))->toOthers();

        $deviceCount = $user->playerDevices()
            ->where('last_seen_at', '>', now()->subMinutes(5))
            ->count();

        return response()->json([
            'data' => [
                'status' => 'synced',
                'devices_notified' => $deviceCount,
            ],
        ]);
    }
}
```

## Frontend Integration

### Pusher Client Setup

```typescript
// composables/useRemotePlayer.ts
import Pusher from 'pusher-js';

export function useRemotePlayer() {
  const playerStore = usePlayerStore();
  const authStore = useAuthStore();
  const deviceId = useDeviceId();

  let pusher: Pusher | null = null;
  let channel: any = null;

  function connect() {
    pusher = new Pusher(import.meta.env.VITE_PUSHER_APP_KEY, {
      cluster: import.meta.env.VITE_PUSHER_APP_CLUSTER,
      authEndpoint: '/broadcasting/auth',
      auth: {
        headers: {
          Authorization: `Bearer ${authStore.token}`,
        },
      },
    });

    channel = pusher.subscribe(`private-player.${authStore.user.id}`);

    channel.bind('player.command', handleCommand);
    channel.bind('player.state', handleStateUpdate);
    channel.bind('queue.sync', handleQueueSync);
  }

  function handleCommand(data: {
    target_device_id: string;
    command: string;
    payload?: any;
  }) {
    if (data.target_device_id !== deviceId.value) {
      return;
    }

    switch (data.command) {
      case 'play':
        playerStore.resume();
        break;
      case 'pause':
        playerStore.pause();
        break;
      case 'next':
        playerStore.next();
        break;
      case 'prev':
        playerStore.previous();
        break;
      case 'seek':
        playerStore.seek(data.payload.position);
        break;
      case 'volume':
        playerStore.setVolume(data.payload.volume);
        break;
    }
  }

  function sendCommand(targetDeviceId: string, command: string, payload?: any) {
    return api.post('/player/control', {
      target_device_id: targetDeviceId,
      command,
      payload,
    });
  }

  function disconnect() {
    if (channel) {
      channel.unbind_all();
      pusher?.unsubscribe(`private-player.${authStore.user.id}`);
    }
    pusher?.disconnect();
  }

  return {
    connect,
    disconnect,
    sendCommand,
  };
}
```

### Device Picker Component

```vue
<template>
  <div class="device-picker">
    <button @click="showPicker = !showPicker">
      <SpeakerIcon />
    </button>

    <div v-if="showPicker" class="device-list">
      <div
        v-for="device in devices"
        :key="device.device_id"
        class="device-item"
        :class="{ active: isCurrentDevice(device) }"
        @click="selectDevice(device)"
      >
        <DeviceIcon :type="device.type" />
        <span>{{ device.name }}</span>
        <span v-if="device.is_playing" class="playing-indicator" />
      </div>
    </div>
  </div>
</template>

<script setup lang="ts">
const { devices, selectDevice, currentDeviceId } = useRemotePlayer();

const isCurrentDevice = (device) => device.device_id === currentDeviceId.value;
</script>
```

## Heartbeat

### Keep Devices Alive

```typescript
// Send heartbeat every 30 seconds
setInterval(async () => {
  try {
    await api.post('/player/heartbeat', {
      device_id: deviceId.value,
      is_playing: playerStore.isPlaying,
      current_song_id: playerStore.currentSong?.id,
      position: playerStore.currentTime,
      volume: playerStore.volume,
    });
  } catch (e) {
    console.error('Heartbeat failed:', e);
  }
}, 30000);
```

### Server-side Heartbeat Handler

```php
public function heartbeat(HeartbeatRequest $request): Response
{
    auth()->user()->playerDevices()
        ->where('device_id', $request->input('device_id'))
        ->update([
            'is_playing' => $request->input('is_playing'),
            'current_song_id' => $request->input('current_song_id'),
            'position' => $request->input('position'),
            'volume' => $request->input('volume'),
            'last_seen_at' => now(),
        ]);

    return response()->noContent();
}
```

## Security

### Channel Authorization

```php
// routes/channels.php
Broadcast::channel('player.{userId}', function (User $user, int $userId) {
    return $user->id === $userId;
});
```

### Device Ownership Verification

All device operations verify the device belongs to the authenticated user:

```php
$device = auth()->user()->playerDevices()
    ->where('device_id', $deviceId)
    ->firstOrFail();
```

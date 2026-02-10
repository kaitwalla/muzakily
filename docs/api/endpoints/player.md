# Player API

Remote player control and device management via Pusher real-time events.

## List Devices

```
GET /api/v1/player/devices
```

Get all registered devices for the authenticated user.

### Example Response

```json
{
  "data": [
    {
      "device_id": "web-abc123",
      "name": "Chrome on MacBook",
      "type": "web",
      "is_playing": true,
      "current_song": {
        "id": "550e8400-e29b-41d4-a716-446655440000",
        "title": "Love Song",
        "artist_name": "The Artist",
        "album_name": "Greatest Hits",
        "length": 245
      },
      "position": 45.5,
      "volume": 0.8,
      "last_seen": "2024-01-20T15:30:00.000000Z"
    },
    {
      "device_id": "mobile-xyz789",
      "name": "iPhone",
      "type": "mobile",
      "is_playing": false,
      "current_song": null,
      "position": 0,
      "volume": 1.0,
      "last_seen": "2024-01-20T10:00:00.000000Z"
    }
  ]
}
```

## Register Device

```
POST /api/v1/player/devices
```

Register a new player device.

### Request Body

| Field | Type | Required | Description |
|-------|------|----------|-------------|
| `device_id` | string | Yes | Unique device identifier (max 64 chars) |
| `name` | string | Yes | Human-readable device name (max 255 chars) |
| `type` | string | Yes | Device type: web, mobile, desktop |

### Example Request

```bash
curl -X POST "https://api.example.com/api/v1/player/devices" \
  -H "Authorization: Bearer {token}" \
  -H "Content-Type: application/json" \
  -d '{
    "device_id": "web-abc123",
    "name": "Chrome on MacBook",
    "type": "web"
  }'
```

### Example Response

```json
{
  "data": {
    "device_id": "web-abc123",
    "name": "Chrome on MacBook",
    "type": "web",
    "is_playing": false,
    "created_at": "2024-01-20T15:30:00.000000Z"
  }
}
```

### Device ID Generation

Generate a unique device ID client-side:

```javascript
function getDeviceId() {
  let deviceId = localStorage.getItem('deviceId');
  if (!deviceId) {
    deviceId = `web-${crypto.randomUUID()}`;
    localStorage.setItem('deviceId', deviceId);
  }
  return deviceId;
}
```

## Unregister Device

```
DELETE /api/v1/player/devices/{device_id}
```

Remove a registered device.

## Send Control Command

```
POST /api/v1/player/control
```

Send a remote control command to another device.

### Request Body

| Field | Type | Required | Description |
|-------|------|----------|-------------|
| `target_device_id` | string | Yes | Device to control |
| `command` | string | Yes | Command to send |
| `payload` | object | No | Command-specific data |

### Commands

| Command | Payload | Description |
|---------|---------|-------------|
| `play` | - | Resume playback |
| `pause` | - | Pause playback |
| `stop` | - | Stop playback and clear position |
| `next` | - | Skip to next song |
| `prev` | - | Go to previous song |
| `seek` | `{position: number}` | Seek to position (seconds) |
| `volume` | `{volume: number}` | Set volume (0-1) |
| `queue_add` | `{song_ids: string[]}` | Add songs to queue |
| `queue_clear` | - | Clear the queue |

### Example Requests

```bash
# Pause playback
curl -X POST "https://api.example.com/api/v1/player/control" \
  -H "Authorization: Bearer {token}" \
  -H "Content-Type: application/json" \
  -d '{
    "target_device_id": "web-abc123",
    "command": "pause"
  }'

# Seek to 1:30
curl -X POST "https://api.example.com/api/v1/player/control" \
  -H "Authorization: Bearer {token}" \
  -H "Content-Type: application/json" \
  -d '{
    "target_device_id": "web-abc123",
    "command": "seek",
    "payload": {"position": 90}
  }'

# Set volume to 50%
curl -X POST "https://api.example.com/api/v1/player/control" \
  -H "Authorization: Bearer {token}" \
  -H "Content-Type: application/json" \
  -d '{
    "target_device_id": "web-abc123",
    "command": "volume",
    "payload": {"volume": 0.5}
  }'
```

### Example Response

```json
{
  "data": {
    "status": "command_sent",
    "target_device_id": "web-abc123",
    "command": "pause"
  }
}
```

## Get Playback State

```
GET /api/v1/player/state
```

Get the current playback state across all devices.

### Example Response

```json
{
  "data": {
    "active_device": {
      "device_id": "web-abc123",
      "name": "Chrome on MacBook"
    },
    "is_playing": true,
    "current_song": {
      "id": "550e8400-e29b-41d4-a716-446655440000",
      "title": "Love Song",
      "artist_name": "The Artist",
      "album_name": "Greatest Hits",
      "length": 245
    },
    "position": 45.5,
    "volume": 0.8,
    "queue": [
      {
        "id": "550e8400-e29b-41d4-a716-446655440000",
        "title": "Love Song",
        ...
      },
      {
        "id": "550e8400-e29b-41d4-a716-446655440001",
        "title": "Next Song",
        ...
      }
    ]
  }
}
```

## Sync Queue

```
POST /api/v1/player/sync
```

Synchronize the queue across all devices.

### Request Body

| Field | Type | Required | Description |
|-------|------|----------|-------------|
| `queue` | array | Yes | Song UUIDs in order |
| `current_index` | integer | No | Current playing index |
| `position` | number | No | Current position in seconds |

### Example Request

```bash
curl -X POST "https://api.example.com/api/v1/player/sync" \
  -H "Authorization: Bearer {token}" \
  -H "Content-Type: application/json" \
  -d '{
    "queue": [
      "550e8400-e29b-41d4-a716-446655440000",
      "550e8400-e29b-41d4-a716-446655440001",
      "550e8400-e29b-41d4-a716-446655440002"
    ],
    "current_index": 0,
    "position": 45.5
  }'
```

### Example Response

```json
{
  "data": {
    "status": "synced",
    "devices_notified": 2
  }
}
```

## Pusher Integration

Commands are delivered via Pusher for real-time updates.

### Subscribe to Commands

```javascript
import Pusher from 'pusher-js';

const pusher = new Pusher('your-app-key', {
  cluster: 'your-cluster',
  authEndpoint: '/broadcasting/auth',
});

const channel = pusher.subscribe(`private-player.${userId}`);

channel.bind('player.command', (data) => {
  if (data.target_device_id === myDeviceId) {
    handleCommand(data.command, data.payload);
  }
});

function handleCommand(command, payload) {
  switch (command) {
    case 'play':
      audio.play();
      break;
    case 'pause':
      audio.pause();
      break;
    case 'seek':
      audio.currentTime = payload.position;
      break;
    case 'volume':
      audio.volume = payload.volume;
      break;
    case 'next':
      playNext();
      break;
    case 'prev':
      playPrevious();
      break;
  }
}
```

### Broadcast State Updates

Keep other devices informed of local changes:

```javascript
audio.addEventListener('play', () => {
  updateDeviceState({ is_playing: true });
});

audio.addEventListener('pause', () => {
  updateDeviceState({ is_playing: false });
});

audio.addEventListener('timeupdate', throttle(() => {
  updateDeviceState({ position: audio.currentTime });
}, 5000));
```

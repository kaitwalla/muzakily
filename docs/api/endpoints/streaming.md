# Streaming API

Audio streaming and download functionality.

## Get Streaming URL

```
GET /api/v1/songs/{id}/stream
```

Returns a presigned URL for streaming the song. The URL is valid for a limited time.

### Query Parameters

| Parameter | Type | Default | Description |
|-----------|------|---------|-------------|
| `format` | string | original | Desired format: mp3, aac, or original |
| `bitrate` | integer | 256 | Desired bitrate in kbps (for transcoded formats) |

### Example Request

```bash
# Stream original format
curl "https://api.example.com/api/v1/songs/{id}/stream" \
  -H "Authorization: Bearer {token}"

# Stream as MP3 at 320kbps
curl "https://api.example.com/api/v1/songs/{id}/stream?format=mp3&bitrate=320" \
  -H "Authorization: Bearer {token}"
```

### Example Response

```json
{
  "data": {
    "url": "https://your-r2-bucket.r2.cloudflarestorage.com/songs/abc123.flac?X-Amz-Algorithm=AWS4-HMAC-SHA256&X-Amz-Credential=...",
    "audio_format": "FLAC",
    "audio_length": 245
  }
}
```

### Using the Streaming URL

The returned URL can be used directly in an HTML5 audio element:

```html
<audio src="https://your-r2-bucket.r2.cloudflarestorage.com/songs/abc123.flac?..." controls></audio>
```

Or with JavaScript:

```javascript
const audio = new Audio(streamingUrl);
audio.play();
```

## Transcoding

When requesting a format different from the original, the server may transcode the audio:

| Original | Requested | Result |
|----------|-----------|--------|
| FLAC | mp3 | Transcoded to MP3 |
| FLAC | aac | Transcoded to AAC |
| FLAC | original | Original FLAC |
| MP3 | original | Original MP3 |
| MP3 | aac | Transcoded to AAC |

### Bitrate Options

For transcoded formats, specify the desired bitrate:

| Bitrate | Quality | Use Case |
|---------|---------|----------|
| 128 | Low | Mobile data saving |
| 192 | Medium | Balanced quality/size |
| 256 | High | Good quality (default) |
| 320 | Highest | Best lossy quality |

```bash
# Low quality for slow connections
curl "https://api.example.com/api/v1/songs/{id}/stream?format=mp3&bitrate=128" \
  -H "Authorization: Bearer {token}"
```

## Download Song

```
GET /api/v1/songs/{id}/download
```

Download the original song file. Returns a 302 redirect to a presigned download URL.

### Example Request

```bash
# Follow redirects to download
curl -L "https://api.example.com/api/v1/songs/{id}/download" \
  -H "Authorization: Bearer {token}" \
  -o song.flac
```

### Response

```
HTTP/1.1 302 Found
Location: https://your-r2-bucket.r2.cloudflarestorage.com/songs/abc123.flac?...&response-content-disposition=attachment
```

The download URL includes a `Content-Disposition: attachment` header to trigger a file download in browsers.

## Local Storage Streaming

```
GET /api/v1/stream/local
```

When using local storage instead of R2, songs are streamed through this endpoint using signed URLs. This endpoint supports HTTP Range requests for seeking.

### Authentication

This endpoint uses URL signature verification instead of Bearer tokens. The signed URL is provided by the `/songs/{id}/stream` endpoint when local storage is configured.

### Query Parameters

| Parameter | Type | Description |
|-----------|------|-------------|
| `path` | string | Path to the audio file |
| `signature` | string | URL signature |
| `expires` | integer | Expiration timestamp |

### HTTP Range Support

The endpoint supports Range requests for audio seeking:

```bash
# Request bytes 0-1023
curl "https://api.example.com/api/v1/stream/local?path=songs/abc.mp3&signature=xyz&expires=123" \
  -H "Range: bytes=0-1023"
```

### Response Headers

For full file requests (200):
```
Content-Type: audio/mpeg
Accept-Ranges: bytes
Content-Length: 5242880
```

For range requests (206):
```
Content-Type: audio/mpeg
Accept-Ranges: bytes
Content-Range: bytes 0-1023/5242880
Content-Length: 1024
```

### Supported Formats

| Extension | MIME Type |
|-----------|-----------|
| .mp3 | audio/mpeg |
| .m4a, .aac | audio/mp4 |
| .flac | audio/flac |
| .wav | audio/wav |
| .ogg | audio/ogg |

## Record Play

After streaming starts, record the play for analytics:

```
POST /api/v1/interactions/play
```

```bash
curl -X POST "https://api.example.com/api/v1/interactions/play" \
  -H "Authorization: Bearer {token}" \
  -H "Content-Type: application/json" \
  -d '{"song_id": "550e8400-e29b-41d4-a716-446655440000"}'
```

This increments the play count and updates the last played timestamp for recently played tracking.

## Streaming Best Practices

### 1. Preload Metadata

Fetch song details before streaming to display in the player:

```javascript
const song = await fetch(`/api/v1/songs/${songId}`).then(r => r.json());
const stream = await fetch(`/api/v1/songs/${songId}/stream`).then(r => r.json());

playerUI.display(song.data);
audio.src = stream.data.url;
```

### 2. Handle Expiration

Presigned URLs expire. If playback fails, fetch a new URL:

```javascript
audio.addEventListener('error', async () => {
  if (audio.error.code === MediaError.MEDIA_ERR_NETWORK) {
    const newStream = await fetch(`/api/v1/songs/${currentSongId}/stream`).then(r => r.json());
    audio.src = newStream.data.url;
    audio.play();
  }
});
```

### 3. Adaptive Quality

Adjust quality based on network conditions:

```javascript
const connection = navigator.connection;
let bitrate = 256;

if (connection) {
  if (connection.saveData || connection.effectiveType === '2g') {
    bitrate = 128;
  } else if (connection.effectiveType === '3g') {
    bitrate = 192;
  }
}

const stream = await fetch(`/api/v1/songs/${songId}/stream?format=mp3&bitrate=${bitrate}`)
  .then(r => r.json());
```

### 4. Gapless Playback

For gapless album playback, prefetch the next song's URL while the current song is playing:

```javascript
audio.addEventListener('timeupdate', async () => {
  const remaining = audio.duration - audio.currentTime;
  if (remaining < 10 && !nextSongUrl) {
    nextSongUrl = await fetch(`/api/v1/songs/${nextSongId}/stream`).then(r => r.json());
  }
});
```

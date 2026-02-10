# Mobile Access

Access Muzakily on your mobile device through the web browser or third-party apps using the API.

## Mobile Web

### Accessing Muzakily

1. Open Safari (iOS) or Chrome (Android)
2. Navigate to your Muzakily URL
3. Log in with your credentials

### Mobile Interface

The interface adapts to small screens:

- **Bottom navigation** replaces sidebar
- **Swipe gestures** for common actions
- **Compact player** at the bottom
- **Full-screen player** on tap

### Add to Home Screen

For app-like experience:

**iOS (Safari):**
1. Open Muzakily in Safari
2. Tap the Share button
3. Select "Add to Home Screen"
4. Name it and tap Add

**Android (Chrome):**
1. Open Muzakily in Chrome
2. Tap the menu (three dots)
3. Select "Add to Home Screen"
4. Confirm

This creates an icon that opens Muzakily without browser UI.

## Mobile Features

### Touch Controls

| Gesture | Action |
|---------|--------|
| Tap | Play/select |
| Long press | Context menu |
| Swipe left | Remove from queue |
| Swipe right | Add to favorites |
| Pull down | Refresh |

### Player Gestures

In full-screen player:

| Gesture | Action |
|---------|--------|
| Swipe up | Show queue |
| Swipe down | Minimize player |
| Tap artwork | Toggle info/controls |

### Offline Support

Currently, Muzakily requires an internet connection. Songs are streamed, not downloaded.

## API for Third-Party Apps

Developers can build mobile apps using the Muzakily API.

### Authentication

Apps authenticate using the same API as the web interface:

```bash
POST /api/v1/auth/login
{
  "email": "user@example.com",
  "password": "password"
}
```

Returns a bearer token for subsequent requests.

### Key Endpoints

| Endpoint | Description |
|----------|-------------|
| `GET /api/v1/songs` | List/search songs |
| `GET /api/v1/songs/{id}/stream` | Get streaming URL |
| `GET /api/v1/playlists` | List playlists |
| `POST /api/v1/interactions/play` | Record play |
| `GET /api/v1/player/devices` | List devices |

### Streaming

Stream URLs are presigned and time-limited. Fetch a new URL if playback fails:

```javascript
const stream = await api.get(`/songs/${songId}/stream`);
audio.src = stream.data.url;
```

### Documentation

Full API documentation: [API Reference](../api/openapi.yaml)

## Mobile Optimization Tips

### Battery Life

- Use lower quality streaming (128kbps) on cellular
- Close the app when not in use
- Disable background refresh if not using remote control

### Data Usage

Estimate data per hour:

| Quality | Data/Hour |
|---------|-----------|
| 128 kbps | ~55 MB |
| 192 kbps | ~85 MB |
| 256 kbps | ~115 MB |
| 320 kbps | ~145 MB |

### Reduce Data

1. Go to Settings
2. Select Audio Quality
3. Choose lower quality for cellular

## Remote Control from Mobile

Control other devices from your phone:

1. Open Muzakily on your phone
2. Tap the device picker
3. Select the device to control
4. Use on-screen controls

See [Remote Control](remote-control.md) for details.

## Troubleshooting

### Audio Stops When Backgrounded

**iOS:** Safari pauses audio when the tab is backgrounded. Solutions:
- Keep Safari in foreground
- Use home screen shortcut (better background support)

**Android:** Chrome supports background audio. If it stops:
- Check battery saver isn't killing the app
- Ensure Chrome has audio permissions

### Slow Performance

- Clear browser cache
- Close other tabs
- Use simpler views (list instead of grid)

### Login Issues

- Ensure correct URL
- Check date/time settings
- Clear cookies if persistent issues

### Streaming Failures

- Check network connection
- Try switching to WiFi
- Lower streaming quality

## Limitations

Current mobile limitations:

- **No offline mode** - Requires internet connection
- **No background download** - Songs stream only
- **iOS background** - May pause in background
- **No lock screen controls** - Depends on browser support

These may be addressed in future updates or native apps.

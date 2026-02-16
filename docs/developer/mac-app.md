# Mac App Implementation Plan

This document outlines the plan for implementing Muzakily features in a native macOS app. The Mac app will integrate with the existing codebase and communicate with the Muzakily backend via the REST API.

## Overview

The Mac app will provide a native music streaming experience with all web features plus macOS-specific capabilities like menu bar integration, keyboard shortcuts, Touch Bar support, seamless Handoff with iOS, local library caching, and portable audio player sync.

## API Base Configuration

```swift
struct APIConfig {
    static let baseURL = "https://your-server.com/api/v1"
    static let authHeader = "Authorization"
    static let bearerPrefix = "Bearer "
}
```

All requests require authentication via Bearer token except `/auth/login`.

---

## Feature Implementation Plan

### Phase 1: Core Authentication & Library Browsing

#### 1.1 Authentication

| Feature | API Endpoint | Method | Notes |
|---------|-------------|--------|-------|
| Login | `/auth/login` | POST | Store token in Keychain |
| Logout | `/auth/logout` | DELETE | Clear Keychain token |
| Get Profile | `/auth/me` | GET | Display user info |
| Update Profile | `/auth/me` | PATCH | Allow name/password change |

**Request: Login**
```json
POST /auth/login
{
  "email": "user@example.com",
  "password": "password"
}
```

**Response: Login**
```json
{
  "data": {
    "token": "1|abc123...",
    "user": {
      "id": 1,
      "uuid": "550e8400-...",
      "name": "John Doe",
      "email": "user@example.com",
      "role": "user"
    }
  }
}
```

**macOS Implementation Notes:**
- Store token in Keychain using `Security` framework
- Use `URLSession` with custom `URLSessionConfiguration` for auth headers
- Handle 401 responses globally to prompt re-authentication
- Support Sign in with Apple for streamlined authentication

---

#### 1.2 Songs Browsing

| Feature | API Endpoint | Method | Notes |
|---------|-------------|--------|-------|
| List Songs | `/songs` | GET | Paginated, with filters |
| Get Song | `/songs/{id}` | GET | Full details with lyrics |
| Recently Played | `/songs/recently-played` | GET | User's recent history |

**Query Parameters for `/songs`:**
```
?search=query          // Search in title/artist/album
&artist_id=uuid        // Filter by artist
&album_id=uuid         // Filter by album
&genre=Rock            // Filter by genre
&smart_folder_id=5     // Filter by smart folder
&format=FLAC           // Filter by format (MP3, AAC, FLAC)
&favorited=true        // Only favorites
&sort=title            // Sort: title, artist_name, album_name, year, created_at
&order=asc             // Order: asc, desc
&per_page=50           // Results per page (max 100)
&page=1                // Page number
```

**macOS Implementation Notes:**
- Use `Codable` for JSON parsing
- Use SwiftUI `List` with `LazyVStack` for efficient scrolling
- Implement `NSTableView` (AppKit) or SwiftUI `Table` for detailed song lists
- Cache song metadata in SwiftData for offline access
- Support column sorting by clicking headers

---

#### 1.3 Albums Browsing

| Feature | API Endpoint | Method | Notes |
|---------|-------------|--------|-------|
| List Albums | `/albums` | GET | With cover art URLs |
| Get Album | `/albums/{id}` | GET | Album details |
| Album Songs | `/albums/{album}/songs` | GET | Ordered by disc/track |

**Query Parameters for `/albums`:**
```
?search=query          // Search album names
&artist_id=uuid        // Filter by artist
&year=2024             // Filter by year
&sort=name             // Sort: name, year, created_at
&order=asc
&per_page=50
```

**macOS Implementation Notes:**
- Use `SDWebImage` or `Kingfisher` for async image loading
- Implement album grid with SwiftUI `LazyVGrid`
- Support multiple view modes: Grid, List, Column Browser
- Cache cover art aggressively (they don't change)
- Double-click album to play all songs

---

#### 1.4 Artists Browsing

| Feature | API Endpoint | Method | Notes |
|---------|-------------|--------|-------|
| List Artists | `/artists` | GET | With image URLs |
| Get Artist | `/artists/{id}` | GET | With bio |
| Artist Albums | `/artists/{artist}/albums` | GET | Ordered by year |
| Artist Songs | `/artists/{artist}/songs` | GET | Paginated |

---

### Phase 2: Audio Playback

#### 2.1 Streaming

| Feature | API Endpoint | Method | Notes |
|---------|-------------|--------|-------|
| Get Stream URL | `/songs/{id}/stream` | GET | Returns presigned URL |
| Download Song | `/songs/{id}/download` | GET | 302 redirect to file |

**Request: Stream**
```
GET /songs/{id}/stream?format=original&bitrate=256
```

**Response: Stream**
```json
{
  "data": {
    "url": "https://r2.example.com/songs/abc?signature=...",
    "audio_format": "MP3",
    "audio_length": 245
  }
}
```

**macOS Implementation Notes:**
- Use `AVPlayer` for streaming playback
- Presigned URLs expire - fetch new URL if playback fails with 403
- Configure audio session for background playback (not strictly required on macOS but good practice)

```swift
// macOS doesn't require explicit audio session configuration like iOS,
// but AVPlayer works the same way
let player = AVPlayer(url: streamURL)
player.play()
```

- Respond to media keys (play/pause, next, previous) via `MPRemoteCommandCenter`
- Support AirPlay output selection

---

#### 2.2 Play Tracking

| Feature | API Endpoint | Method | Notes |
|---------|-------------|--------|-------|
| Record Play | `/interactions/play` | POST | Call after ~30s of playback |

**Request:**
```json
POST /interactions/play
{
  "song_id": "550e8400-e29b-41d4-a716-446655440000"
}
```

**macOS Implementation Notes:**
- Track playback time locally
- Call endpoint when song plays for 30+ seconds
- Queue requests if offline, sync when back online

---

### Phase 3: Playlists

#### 3.1 Regular Playlists

| Feature | API Endpoint | Method | Notes |
|---------|-------------|--------|-------|
| List Playlists | `/playlists` | GET | All user playlists |
| Get Playlist | `/playlists/{id}` | GET | Playlist details |
| Create Playlist | `/playlists` | POST | Name + optional songs |
| Update Playlist | `/playlists/{id}` | PATCH | Update name/description |
| Delete Playlist | `/playlists/{id}` | DELETE | Remove playlist |
| Get Songs | `/playlists/{id}/songs` | GET | Songs in playlist |
| Add Songs | `/playlists/{id}/songs` | POST | Add songs |
| Remove Songs | `/playlists/{id}/songs` | DELETE | Remove songs |
| Reorder Songs | `/playlists/{id}/songs/reorder` | PUT | Change song order |

**Create Playlist Request:**
```json
POST /playlists
{
  "name": "My Playlist",
  "description": "Optional description",
  "song_ids": ["uuid1", "uuid2"]
}
```

**Add Songs Request:**
```json
POST /playlists/{id}/songs
{
  "song_ids": ["uuid1", "uuid2"],
  "position": 5  // Optional: insert at position
}
```

**Reorder Songs Request:**
```json
PUT /playlists/{id}/songs/reorder
{
  "song_ids": ["uuid2", "uuid1", "uuid3"]  // New order
}
```

**macOS Implementation Notes:**
- Support drag-and-drop to add songs to playlists
- Allow drag-and-drop reordering within playlist
- Right-click context menus for playlist operations
- Keyboard shortcuts: ⌘N for new playlist, Delete key to remove

---

#### 3.2 Smart Playlists

| Feature | API Endpoint | Method | Notes |
|---------|-------------|--------|-------|
| Create Smart Playlist | `/playlists` | POST | With `is_smart: true` and rules |
| Get Smart Playlist Songs | `/playlists/{id}/songs` | GET | Dynamically evaluated |

**Create Smart Playlist:**
```json
POST /playlists
{
  "name": "Recently Added Rock",
  "is_smart": true,
  "rules": [
    {
      "logic": "and",
      "rules": [
        { "field": "genre", "operator": "equals", "value": "Rock" },
        { "field": "created_at", "operator": "after", "value": "2024-01-01" }
      ]
    }
  ]
}
```

**Available Rule Fields:**
- `title` - Song title
- `artist` - Artist name
- `album` - Album name
- `genre` - Genre
- `year` - Release year
- `play_count` - Number of plays
- `format` - Audio format (MP3, AAC, FLAC)
- `tag` - Tag name/ID
- `created_at` - Date added
- `last_played_at` - Last played date

**Available Operators:**
- `equals`, `not_equals`
- `contains`, `not_contains`
- `starts_with`, `ends_with`
- `greater_than`, `less_than`
- `before`, `after` (for dates)

**macOS Implementation Notes:**
- Smart playlists cannot have songs manually added/removed
- Display a rule summary in the sidebar
- Implement visual rule builder with NSPredicateEditor-style UI
- Show smart playlist icon to differentiate from regular playlists

---

### Phase 4: Organization Features

#### 4.1 Smart Folders

| Feature | API Endpoint | Method | Notes |
|---------|-------------|--------|-------|
| List Folders | `/smart-folders` | GET | Hierarchical tree |
| Folder Songs | `/smart-folders/{id}/songs` | GET | Songs in folder |

**Response: List Folders (hierarchical)**
```json
{
  "data": [
    {
      "id": 1,
      "name": "Music",
      "path": "Music",
      "depth": 0,
      "song_count": 1500,
      "children": [
        {
          "id": 2,
          "name": "Rock",
          "path": "Music/Rock",
          "depth": 1,
          "song_count": 400,
          "children": []
        }
      ]
    }
  ]
}
```

**macOS Implementation Notes:**
- Display as expandable outline in sidebar
- Use `NSOutlineView` or SwiftUI `OutlineGroup`

---

#### 4.2 Tags

| Feature | API Endpoint | Method | Notes |
|---------|-------------|--------|-------|
| List Tags | `/tags` | GET | Hierarchical or flat |
| Get Tag | `/tags/{id}` | GET | Tag details |
| Create Tag | `/tags` | POST | New tag |
| Update Tag | `/tags/{id}` | PATCH | Edit tag |
| Delete Tag | `/tags/{id}` | DELETE | Remove tag |
| Tag Songs | `/tags/{id}/songs` | GET | Songs with tag |
| Add Tags to Song | `/songs/{id}/tags` | POST | Apply tags |
| Remove Tags | `/songs/{id}/tags` | DELETE | Remove tags |

**Query Parameters for `/tags`:**
```
?flat=true    // Return flat list instead of hierarchy
```

**Create Tag:**
```json
POST /tags
{
  "name": "Workout",
  "color": "#ff5500",
  "parent_id": 5,  // Optional: make child of tag 5
  "auto_assign_pattern": ".*workout.*"  // Optional: regex for auto-assignment
}
```

**Add Tags to Song:**
```json
POST /songs/{id}/tags
{
  "tag_ids": [1, 5, 12]
}
```

**macOS Implementation Notes:**
- Display colored tag badges on songs
- Support multi-select tagging via context menu
- Show tag browser in sidebar

---

#### 4.3 Favorites

| Feature | API Endpoint | Method | Notes |
|---------|-------------|--------|-------|
| List Favorites | `/favorites` | GET | All types or filtered |
| Add Favorite | `/favorites` | POST | Add to favorites |
| Remove Favorite | `/favorites` | DELETE | Remove from favorites |

**Query Parameters:**
```
?type=song    // Filter: song, album, artist, playlist
```

**Add/Remove Favorite:**
```json
POST /favorites
{
  "type": "song",
  "id": "550e8400-e29b-41d4-a716-446655440000"
}
```

**macOS Implementation Notes:**
- Show heart icon on songs, toggle with keyboard shortcut (⌘L for Love)
- Sync favorites state with server
- Cache locally for offline access
- Add "Favorites" section in sidebar

---

### Phase 5: Search

| Feature | API Endpoint | Method | Notes |
|---------|-------------|--------|-------|
| Full-Text Search | `/search` | GET | Searches songs, albums, artists |

**Query Parameters:**
```
?q=love                    // Search query (min 2 chars)
&type=song                 // Optional: search only songs/albums/artists
&limit=10                  // Results per type (max 50)
&filters[year]=2024        // Filter by year
&filters[tag]=workout      // Filter by tag
&filters[genre]=Rock       // Filter by genre
&filters[format]=FLAC      // Filter by format
```

**Response:**
```json
{
  "data": {
    "songs": {
      "data": [...],
      "total": 45
    },
    "albums": {
      "data": [...],
      "total": 12
    },
    "artists": {
      "data": [...],
      "total": 3
    }
  }
}
```

**macOS Implementation Notes:**
- Implement Spotlight-style search field (⌘F to focus)
- Support search filters via toolbar controls
- Implement debouncing (300ms) before API calls
- Show recent searches in dropdown
- Display results in sectioned list with type headers
- Support ⌘+number shortcuts to jump to sections

---

### Phase 6: Multi-Device & Remote Control

#### 6.1 Device Management

| Feature | API Endpoint | Method | Notes |
|---------|-------------|--------|-------|
| List Devices | `/player/devices` | GET | All user devices |
| Register Device | `/player/devices` | POST | Add this device |
| Unregister | `/player/devices/{id}` | DELETE | Remove device |

**Register Device:**
```json
POST /player/devices
{
  "device_id": "mac-abc123",          // Unique ID
  "name": "John's MacBook Pro",       // User-friendly name
  "type": "desktop"                   // web, mobile, desktop
}
```

**macOS Implementation Notes:**
- Generate unique device ID and persist in Keychain
- Use `Host.current().localizedName` for device name
- Register on app launch
- Update `last_seen` periodically

```swift
import Foundation

func getDeviceIdentifier() -> String {
    // Check Keychain first
    if let existingId = KeychainHelper.get("device_id") {
        return existingId
    }

    // Generate new UUID
    let newId = "mac-\(UUID().uuidString)"
    KeychainHelper.set("device_id", value: newId)
    return newId
}

func getDeviceName() -> String {
    return Host.current().localizedName ?? "Mac"
}
```

---

#### 6.2 Remote Player Control

| Feature | API Endpoint | Method | Notes |
|---------|-------------|--------|-------|
| Send Command | `/player/control` | POST | Control another device |
| Get State | `/player/state` | GET | Current playback state |
| Sync Queue | `/player/sync` | POST | Sync queue across devices |

**Send Command:**
```json
POST /player/control
{
  "target_device_id": "web-xyz789",
  "command": "play",               // play, pause, stop, next, prev, seek, volume, queue_add, queue_clear
  "payload": {                     // Command-specific data
    "position": 45.5,              // For seek
    "volume": 0.8,                 // For volume (0-1)
    "song_id": "uuid"              // For queue_add
  }
}
```

**Get State:**
```json
{
  "data": {
    "active_device": {
      "device_id": "web-xyz789",
      "name": "Living Room"
    },
    "is_playing": true,
    "current_song": { ... },
    "position": 45.5,
    "volume": 0.8,
    "queue": [ ... ]
  }
}
```

**macOS Implementation Notes:**
- Implement Pusher client for real-time updates
- Subscribe to user's private channel for commands
- Update UI when receiving remote commands
- Show device picker in Now Playing window/toolbar
- Support Handoff to/from iOS devices

---

### Phase 7: File Upload (Optional)

| Feature | API Endpoint | Method | Notes |
|---------|-------------|--------|-------|
| Upload File | `/upload` | POST | Multipart form data |

**Request:**
```
POST /upload
Content-Type: multipart/form-data

file: <binary data>
```

**Response (202 Accepted):**
```json
{
  "data": {
    "job_id": "abc123",
    "status": "processing",
    "filename": "song.mp3"
  }
}
```

**macOS Implementation Notes:**
- Support drag-and-drop from Finder
- Use `URLSession` with `uploadTask`
- Show progress in Dock icon badge
- Support batch uploads
- Max file size: 100MB
- Supported formats: mp3, m4a, flac

---

### Phase 8: Admin Features (Admin Users Only)

#### 8.1 User Management

| Feature | API Endpoint | Method | Notes |
|---------|-------------|--------|-------|
| List Users | `/admin/users` | GET | Paginated |
| Get User | `/admin/users/{id}` | GET | User details |
| Create User | `/admin/users` | POST | New user |
| Update User | `/admin/users/{id}` | PATCH | Edit user |
| Delete User | `/admin/users/{id}` | DELETE | Remove user |

#### 8.2 Library Management

| Feature | API Endpoint | Method | Notes |
|---------|-------------|--------|-------|
| Trigger Scan | `/admin/library/scan` | POST | Start library scan |
| Scan Status | `/admin/library/scan/status` | GET | Check progress |
| Enrich Metadata | `/admin/metadata/enrich` | POST | Fetch metadata |

---

## macOS-Specific Features

### Menu Bar App (Optional Mini Player)

```swift
import SwiftUI

@main
struct MuzakilyApp: App {
    @NSApplicationDelegateAdaptor(AppDelegate.self) var appDelegate

    var body: some Scene {
        WindowGroup {
            ContentView()
        }
        .commands {
            MuzakilyCommands()
        }

        // Menu bar extra (mini player)
        MenuBarExtra("Muzakily", systemImage: "music.note") {
            MenuBarPlayerView()
        }
        .menuBarExtraStyle(.window)
    }
}

struct MenuBarPlayerView: View {
    @EnvironmentObject var player: PlayerManager

    var body: some View {
        VStack(spacing: 12) {
            // Album art
            AsyncImage(url: player.currentSong?.albumCoverURL) { image in
                image.resizable().aspectRatio(contentMode: .fit)
            } placeholder: {
                Image(systemName: "music.note")
            }
            .frame(width: 200, height: 200)
            .cornerRadius(8)

            // Song info
            VStack(spacing: 4) {
                Text(player.currentSong?.title ?? "Not Playing")
                    .font(.headline)
                    .lineLimit(1)
                Text(player.currentSong?.artistName ?? "")
                    .font(.subheadline)
                    .foregroundColor(.secondary)
                    .lineLimit(1)
            }

            // Progress
            ProgressView(value: player.progress)

            // Controls
            HStack(spacing: 20) {
                Button(action: player.previous) {
                    Image(systemName: "backward.fill")
                }
                .buttonStyle(.plain)

                Button(action: player.togglePlayPause) {
                    Image(systemName: player.isPlaying ? "pause.fill" : "play.fill")
                        .font(.title)
                }
                .buttonStyle(.plain)

                Button(action: player.next) {
                    Image(systemName: "forward.fill")
                }
                .buttonStyle(.plain)
            }

            // Volume
            HStack {
                Image(systemName: "speaker.fill")
                Slider(value: $player.volume, in: 0...1)
                Image(systemName: "speaker.wave.3.fill")
            }
            .padding(.horizontal)
        }
        .padding()
        .frame(width: 280)
    }
}
```

### Keyboard Shortcuts & Menu Commands

```swift
struct MuzakilyCommands: Commands {
    @FocusedBinding(\.selectedSongs) var selectedSongs

    var body: some Commands {
        // Playback menu
        CommandMenu("Playback") {
            Button("Play/Pause") {
                PlayerManager.shared.togglePlayPause()
            }
            .keyboardShortcut(.space, modifiers: [])

            Button("Next Track") {
                PlayerManager.shared.next()
            }
            .keyboardShortcut(.rightArrow, modifiers: .command)

            Button("Previous Track") {
                PlayerManager.shared.previous()
            }
            .keyboardShortcut(.leftArrow, modifiers: .command)

            Divider()

            Button("Increase Volume") {
                PlayerManager.shared.increaseVolume()
            }
            .keyboardShortcut(.upArrow, modifiers: .command)

            Button("Decrease Volume") {
                PlayerManager.shared.decreaseVolume()
            }
            .keyboardShortcut(.downArrow, modifiers: .command)

            Divider()

            Button("Shuffle") {
                PlayerManager.shared.toggleShuffle()
            }
            .keyboardShortcut("S", modifiers: .command)

            Button("Repeat") {
                PlayerManager.shared.toggleRepeat()
            }
            .keyboardShortcut("R", modifiers: .command)
        }

        // Song menu
        CommandMenu("Song") {
            Button("Love") {
                // Toggle favorite
            }
            .keyboardShortcut("L", modifiers: .command)

            Button("Add to Playlist...") {
                // Show playlist picker
            }
            .keyboardShortcut("P", modifiers: [.command, .shift])

            Button("Show in Finder") {
                // For downloaded songs
            }
            .keyboardShortcut("R", modifiers: [.command, .shift])

            Divider()

            Button("Get Info") {
                // Show song details
            }
            .keyboardShortcut("I", modifiers: .command)
        }

        // Replace standard File menu items
        CommandGroup(replacing: .newItem) {
            Button("New Playlist") {
                // Create new playlist
            }
            .keyboardShortcut("N", modifiers: .command)

            Button("New Smart Playlist...") {
                // Create smart playlist
            }
            .keyboardShortcut("N", modifiers: [.command, .option])
        }
    }
}
```

### Now Playing Info & Media Keys

```swift
import MediaPlayer

class NowPlayingManager {
    static let shared = NowPlayingManager()

    func updateNowPlaying(song: Song, isPlaying: Bool, currentTime: Double) {
        var nowPlayingInfo = [String: Any]()

        nowPlayingInfo[MPMediaItemPropertyTitle] = song.title
        nowPlayingInfo[MPMediaItemPropertyArtist] = song.artistName
        nowPlayingInfo[MPMediaItemPropertyAlbumTitle] = song.albumName
        nowPlayingInfo[MPMediaItemPropertyPlaybackDuration] = Double(song.length)
        nowPlayingInfo[MPNowPlayingInfoPropertyElapsedPlaybackTime] = currentTime
        nowPlayingInfo[MPNowPlayingInfoPropertyPlaybackRate] = isPlaying ? 1.0 : 0.0

        // Set artwork
        if let artwork = ImageCache.shared.get(song.albumCover) {
            nowPlayingInfo[MPMediaItemPropertyArtwork] = MPMediaItemArtwork(
                boundsSize: artwork.size
            ) { _ in artwork }
        }

        MPNowPlayingInfoCenter.default().nowPlayingInfo = nowPlayingInfo
    }

    func setupRemoteCommandCenter() {
        let commandCenter = MPRemoteCommandCenter.shared()

        commandCenter.playCommand.addTarget { _ in
            PlayerManager.shared.play()
            return .success
        }

        commandCenter.pauseCommand.addTarget { _ in
            PlayerManager.shared.pause()
            return .success
        }

        commandCenter.togglePlayPauseCommand.addTarget { _ in
            PlayerManager.shared.togglePlayPause()
            return .success
        }

        commandCenter.nextTrackCommand.addTarget { _ in
            PlayerManager.shared.next()
            return .success
        }

        commandCenter.previousTrackCommand.addTarget { _ in
            PlayerManager.shared.previous()
            return .success
        }

        commandCenter.changePlaybackPositionCommand.addTarget { event in
            guard let event = event as? MPChangePlaybackPositionCommandEvent else {
                return .commandFailed
            }
            PlayerManager.shared.seek(to: event.positionTime)
            return .success
        }
    }
}
```

### Touch Bar Support

```swift
import SwiftUI

extension NSTouchBarItem.Identifier {
    static let playPause = NSTouchBarItem.Identifier("com.muzakily.playPause")
    static let previous = NSTouchBarItem.Identifier("com.muzakily.previous")
    static let next = NSTouchBarItem.Identifier("com.muzakily.next")
    static let nowPlaying = NSTouchBarItem.Identifier("com.muzakily.nowPlaying")
    static let scrubber = NSTouchBarItem.Identifier("com.muzakily.scrubber")
}

class TouchBarProvider: NSObject, NSTouchBarDelegate {
    func makeTouchBar() -> NSTouchBar {
        let touchBar = NSTouchBar()
        touchBar.delegate = self
        touchBar.defaultItemIdentifiers = [
            .previous,
            .playPause,
            .next,
            .flexibleSpace,
            .nowPlaying,
            .scrubber
        ]
        return touchBar
    }

    func touchBar(_ touchBar: NSTouchBar, makeItemForIdentifier identifier: NSTouchBarItem.Identifier) -> NSTouchBarItem? {
        switch identifier {
        case .playPause:
            let item = NSCustomTouchBarItem(identifier: identifier)
            let button = NSButton(
                image: NSImage(systemSymbolName: PlayerManager.shared.isPlaying ? "pause.fill" : "play.fill", accessibilityDescription: nil)!,
                target: self,
                action: #selector(togglePlayPause)
            )
            item.view = button
            return item

        case .previous:
            let item = NSCustomTouchBarItem(identifier: identifier)
            let button = NSButton(
                image: NSImage(systemSymbolName: "backward.fill", accessibilityDescription: nil)!,
                target: self,
                action: #selector(previousTrack)
            )
            item.view = button
            return item

        case .next:
            let item = NSCustomTouchBarItem(identifier: identifier)
            let button = NSButton(
                image: NSImage(systemSymbolName: "forward.fill", accessibilityDescription: nil)!,
                target: self,
                action: #selector(nextTrack)
            )
            item.view = button
            return item

        case .nowPlaying:
            let item = NSCustomTouchBarItem(identifier: identifier)
            let label = NSTextField(labelWithString: PlayerManager.shared.currentSong?.title ?? "Not Playing")
            label.lineBreakMode = .byTruncatingTail
            item.view = label
            return item

        case .scrubber:
            let item = NSSliderTouchBarItem(identifier: identifier)
            item.slider.minValue = 0
            item.slider.maxValue = Double(PlayerManager.shared.currentSong?.length ?? 1)
            item.slider.doubleValue = PlayerManager.shared.currentTime
            item.target = self
            item.action = #selector(scrub(_:))
            return item

        default:
            return nil
        }
    }

    @objc func togglePlayPause() {
        PlayerManager.shared.togglePlayPause()
    }

    @objc func previousTrack() {
        PlayerManager.shared.previous()
    }

    @objc func nextTrack() {
        PlayerManager.shared.next()
    }

    @objc func scrub(_ sender: NSSliderTouchBarItem) {
        PlayerManager.shared.seek(to: sender.slider.doubleValue)
    }
}
```

### Dock Integration

```swift
class DockManager {
    static let shared = DockManager()

    func updateDockIcon(with artwork: NSImage?) {
        if let artwork = artwork {
            NSApp.applicationIconImage = artwork
        } else {
            // Reset to default app icon
            NSApp.applicationIconImage = nil
        }
    }

    func setupDockMenu() -> NSMenu {
        let menu = NSMenu()

        let currentSong = PlayerManager.shared.currentSong

        // Now playing info
        if let song = currentSong {
            let nowPlayingItem = NSMenuItem(title: song.title, action: nil, keyEquivalent: "")
            nowPlayingItem.isEnabled = false
            menu.addItem(nowPlayingItem)

            let artistItem = NSMenuItem(title: song.artistName, action: nil, keyEquivalent: "")
            artistItem.isEnabled = false
            menu.addItem(artistItem)

            menu.addItem(NSMenuItem.separator())
        }

        // Playback controls
        let playPauseItem = NSMenuItem(
            title: PlayerManager.shared.isPlaying ? "Pause" : "Play",
            action: #selector(AppDelegate.togglePlayPause),
            keyEquivalent: ""
        )
        menu.addItem(playPauseItem)

        menu.addItem(NSMenuItem(title: "Next", action: #selector(AppDelegate.nextTrack), keyEquivalent: ""))
        menu.addItem(NSMenuItem(title: "Previous", action: #selector(AppDelegate.previousTrack), keyEquivalent: ""))

        return menu
    }
}
```

### Handoff Support

```swift
// Info.plist
/*
<key>NSUserActivityTypes</key>
<array>
    <string>com.muzakily.nowPlaying</string>
    <string>com.muzakily.browsing</string>
</array>
*/

class HandoffManager {
    static let shared = HandoffManager()

    func startNowPlayingActivity(song: Song) {
        let activity = NSUserActivity(activityType: "com.muzakily.nowPlaying")
        activity.title = "Listening to \(song.title)"
        activity.isEligibleForHandoff = true
        activity.userInfo = [
            "songId": song.id.uuidString,
            "position": PlayerManager.shared.currentTime
        ]
        activity.becomeCurrent()
    }

    func continueActivity(_ activity: NSUserActivity) {
        guard activity.activityType == "com.muzakily.nowPlaying",
              let songIdString = activity.userInfo?["songId"] as? String,
              let songId = UUID(uuidString: songIdString) else {
            return
        }

        let position = activity.userInfo?["position"] as? Double ?? 0

        // Fetch song and resume playback
        Task {
            if let song = await APIClient.shared.getSong(id: songId) {
                await PlayerManager.shared.play(song: song, at: position)
            }
        }
    }
}
```

### Notification Center Integration

```swift
import UserNotifications

class NotificationManager {
    static let shared = NotificationManager()

    func requestAuthorization() async {
        let center = UNUserNotificationCenter.current()
        do {
            try await center.requestAuthorization(options: [.alert, .sound])
        } catch {
            print("Notification authorization failed: \(error)")
        }
    }

    func showNowPlaying(song: Song) {
        let content = UNMutableNotificationContent()
        content.title = song.title
        content.subtitle = song.artistName
        content.body = song.albumName
        content.categoryIdentifier = "NOW_PLAYING"

        // Add album art as attachment
        if let imageURL = song.albumCoverURL,
           let data = try? Data(contentsOf: imageURL),
           let attachment = createImageAttachment(data: data) {
            content.attachments = [attachment]
        }

        let request = UNNotificationRequest(
            identifier: "nowPlaying",
            content: content,
            trigger: nil
        )

        UNUserNotificationCenter.current().add(request)
    }

    private func createImageAttachment(data: Data) -> UNNotificationAttachment? {
        let tempDir = FileManager.default.temporaryDirectory
        let imageURL = tempDir.appendingPathComponent("albumArt.jpg")

        do {
            try data.write(to: imageURL)
            return try UNNotificationAttachment(identifier: "albumArt", url: imageURL, options: nil)
        } catch {
            return nil
        }
    }
}
```

### Offline Downloads

**Local Storage Structure:**
```
~/Library/Application Support/Muzakily/
  downloads/
    {song_id}/
      audio.mp3
      metadata.json
      artwork.jpg
```

**SwiftData Model:**
```swift
import SwiftData

@Model
class DownloadedSong {
    @Attribute(.unique) var id: UUID
    var title: String
    var artistName: String
    var albumName: String
    var localPath: String
    var downloadedAt: Date
    var fileSize: Int64

    init(id: UUID, title: String, artistName: String, albumName: String, localPath: String, downloadedAt: Date, fileSize: Int64) {
        self.id = id
        self.title = title
        self.artistName = artistName
        self.albumName = albumName
        self.localPath = localPath
        self.downloadedAt = downloadedAt
        self.fileSize = fileSize
    }
}
```

**Download Manager:**
```swift
class DownloadManager {
    static let shared = DownloadManager()

    private lazy var session: URLSession = {
        let config = URLSessionConfiguration.background(withIdentifier: "com.muzakily.downloads")
        config.isDiscretionary = false
        return URLSession(configuration: config, delegate: self, delegateQueue: nil)
    }()

    func download(song: Song) async throws {
        // Get stream URL
        let streamResponse = try await APIClient.shared.getStreamURL(songId: song.id)

        // Create download task
        let task = session.downloadTask(with: streamResponse.url)
        task.taskDescription = song.id.uuidString
        task.resume()
    }

    func deleteDownload(songId: UUID) throws {
        let path = getDownloadPath(for: songId)
        try FileManager.default.removeItem(at: path)
    }

    private func getDownloadPath(for songId: UUID) -> URL {
        let appSupport = FileManager.default.urls(for: .applicationSupportDirectory, in: .userDomainMask).first!
        return appSupport.appendingPathComponent("Muzakily/downloads/\(songId.uuidString)")
    }
}

extension DownloadManager: URLSessionDownloadDelegate {
    func urlSession(_ session: URLSession, downloadTask: URLSessionDownloadTask, didFinishDownloadingTo location: URL) {
        guard let songIdString = downloadTask.taskDescription,
              let songId = UUID(uuidString: songIdString) else { return }

        let destinationPath = getDownloadPath(for: songId)

        do {
            try FileManager.default.createDirectory(at: destinationPath, withIntermediateDirectories: true)
            try FileManager.default.moveItem(at: location, to: destinationPath.appendingPathComponent("audio.mp3"))
        } catch {
            print("Download save failed: \(error)")
        }
    }

    func urlSession(_ session: URLSession, downloadTask: URLSessionDownloadTask, didWriteData bytesWritten: Int64, totalBytesWritten: Int64, totalBytesExpectedToWrite: Int64) {
        let progress = Double(totalBytesWritten) / Double(totalBytesExpectedToWrite)

        DispatchQueue.main.async {
            // Update UI with download progress
            NotificationCenter.default.post(
                name: .downloadProgress,
                object: nil,
                userInfo: ["taskId": downloadTask.taskDescription ?? "", "progress": progress]
            )
        }
    }
}
```

---

### Phase 9: Local Library Cache

The Mac app can optionally use a local folder containing music files, avoiding the need to re-download an entire library. This enables hybrid usage: stream songs not available locally, play local files instantly.

#### 9.1 Local Library Configuration

**Settings:**
```swift
struct LocalLibrarySettings: Codable {
    var enabled: Bool = false
    var libraryPath: URL?                    // e.g., ~/Music/Muzakily
    var watchForChanges: Bool = true         // Monitor folder for new files
    var preferLocalPlayback: Bool = true     // Use local file when available
    var downloadNewSongs: Bool = true        // Auto-download played songs
}
```

**macOS Implementation Notes:**
- Use `NSOpenPanel` to let user select library folder
- Request read access via Security-Scoped Bookmarks (for sandboxed apps)
- Store bookmark data in UserDefaults for persistent access

```swift
func selectLibraryFolder() {
    let panel = NSOpenPanel()
    panel.canChooseDirectories = true
    panel.canChooseFiles = false
    panel.allowsMultipleSelection = false
    panel.message = "Select your local music library folder"

    panel.begin { response in
        guard response == .OK, let url = panel.url else { return }

        // Create security-scoped bookmark for sandbox access
        do {
            let bookmarkData = try url.bookmarkData(
                options: .withSecurityScope,
                includingResourceValuesForKeys: nil,
                relativeTo: nil
            )
            UserDefaults.standard.set(bookmarkData, forKey: "localLibraryBookmark")
            LocalLibraryManager.shared.setLibraryPath(url)
        } catch {
            print("Failed to create bookmark: \(error)")
        }
    }
}
```

---

#### 9.2 Local Library Scanning

**Scan Process:**
1. Recursively enumerate all audio files in the library folder
2. Extract metadata using AVFoundation or ID3 parsing
3. Match against server library by UUID, filename hash, or metadata fingerprint
4. Store mapping between server song ID and local file path

**Local Library Index (SwiftData):**
```swift
@Model
class LocalSong {
    @Attribute(.unique) var localPath: String      // Relative path from library root
    var serverSongId: UUID?                        // Matched server song ID (if any)
    var title: String
    var artistName: String
    var albumName: String
    var duration: Int
    var fileSize: Int64
    var lastModified: Date
    var fingerprint: String?                       // Audio fingerprint for matching
    var matchStatus: MatchStatus

    enum MatchStatus: String, Codable {
        case matched       // Found on server
        case localOnly     // Only exists locally
        case pending       // Not yet checked
    }
}
```

**Scanning Implementation:**
```swift
class LocalLibraryScanner {
    func scan(libraryPath: URL) async throws -> [LocalSong] {
        var songs: [LocalSong] = []

        let resourceKeys: Set<URLResourceKey> = [
            .isRegularFileKey, .fileSizeKey, .contentModificationDateKey
        ]

        let enumerator = FileManager.default.enumerator(
            at: libraryPath,
            includingPropertiesForKeys: Array(resourceKeys),
            options: [.skipsHiddenFiles]
        )

        while let fileURL = enumerator?.nextObject() as? URL {
            guard isAudioFile(fileURL) else { continue }

            let metadata = try await extractMetadata(from: fileURL)
            let relativePath = fileURL.path.replacingOccurrences(
                of: libraryPath.path + "/",
                with: ""
            )

            let localSong = LocalSong(
                localPath: relativePath,
                title: metadata.title,
                artistName: metadata.artist,
                albumName: metadata.album,
                duration: metadata.duration,
                fileSize: metadata.fileSize,
                lastModified: metadata.modificationDate,
                matchStatus: .pending
            )
            songs.append(localSong)
        }

        return songs
    }

    private func isAudioFile(_ url: URL) -> Bool {
        let audioExtensions = ["mp3", "m4a", "flac", "aac", "wav", "aiff"]
        return audioExtensions.contains(url.pathExtension.lowercased())
    }

    private func extractMetadata(from url: URL) async throws -> AudioMetadata {
        let asset = AVAsset(url: url)
        let metadata = try await asset.load(.metadata)

        var title = url.deletingPathExtension().lastPathComponent
        var artist = "Unknown Artist"
        var album = "Unknown Album"

        for item in metadata {
            guard let key = item.commonKey else { continue }

            switch key {
            case .commonKeyTitle:
                title = try await item.load(.stringValue) ?? title
            case .commonKeyArtist:
                artist = try await item.load(.stringValue) ?? artist
            case .commonKeyAlbumName:
                album = try await item.load(.stringValue) ?? album
            default:
                break
            }
        }

        let duration = try await asset.load(.duration)
        let resourceValues = try url.resourceValues(forKeys: [.fileSizeKey, .contentModificationDateKey])

        return AudioMetadata(
            title: title,
            artist: artist,
            album: album,
            duration: Int(duration.seconds),
            fileSize: Int64(resourceValues.fileSize ?? 0),
            modificationDate: resourceValues.contentModificationDate ?? Date()
        )
    }
}
```

---

#### 9.3 Matching Local Files to Server Library

**Matching Strategy (in priority order):**
1. **Exact path match**: If server stores relative paths
2. **Filename + metadata match**: Title + Artist + Album + Duration
3. **Audio fingerprint**: AcoustID/Chromaprint for fuzzy matching

```swift
class LibraryMatcher {
    func matchLocalToServer(localSongs: [LocalSong]) async throws {
        // Fetch all songs from server (paginated)
        let serverSongs = try await fetchAllServerSongs()

        // Build lookup indexes
        let serverByFingerprint = Dictionary(grouping: serverSongs.filter { $0.fingerprint != nil }) {
            $0.fingerprint!
        }
        let serverByMetadata = Dictionary(grouping: serverSongs) {
            "\($0.title.lowercased())|\($0.artistName.lowercased())|\($0.length)"
        }

        for localSong in localSongs {
            // Try fingerprint match first
            if let fingerprint = localSong.fingerprint,
               let matches = serverByFingerprint[fingerprint],
               let match = matches.first {
                localSong.serverSongId = match.id
                localSong.matchStatus = .matched
                continue
            }

            // Try metadata match
            let metadataKey = "\(localSong.title.lowercased())|\(localSong.artistName.lowercased())|\(localSong.duration)"
            if let matches = serverByMetadata[metadataKey],
               let match = matches.first {
                localSong.serverSongId = match.id
                localSong.matchStatus = .matched
                continue
            }

            // No match found
            localSong.matchStatus = .localOnly
        }

        // Save changes
        try modelContext.save()
    }

    private func fetchAllServerSongs() async throws -> [Song] {
        var allSongs: [Song] = []
        var page = 1

        while true {
            let response = try await APIClient.shared.getSongs(page: page, perPage: 100)
            allSongs.append(contentsOf: response.data)

            if page >= response.meta.lastPage {
                break
            }
            page += 1
        }

        return allSongs
    }
}
```

---

#### 9.4 Hybrid Playback

**Playback Decision Logic:**
```swift
class HybridPlayerManager {
    func getPlaybackURL(for song: Song) async throws -> URL {
        // Check if local file exists and is preferred
        if LocalLibrarySettings.shared.preferLocalPlayback,
           let localSong = try await findLocalSong(for: song.id) {
            let libraryPath = LocalLibrarySettings.shared.libraryPath!
            let localURL = libraryPath.appendingPathComponent(localSong.localPath)

            if FileManager.default.fileExists(atPath: localURL.path) {
                return localURL
            }
        }

        // Check downloads folder
        let downloadPath = DownloadManager.shared.getDownloadPath(for: song.id)
        let audioPath = downloadPath.appendingPathComponent("audio.mp3")
        if FileManager.default.fileExists(atPath: audioPath.path) {
            return audioPath
        }

        // Fall back to streaming
        let streamResponse = try await APIClient.shared.getStreamURL(songId: song.id)

        // Optionally queue for download
        if LocalLibrarySettings.shared.downloadNewSongs {
            Task {
                try? await DownloadManager.shared.download(song: song)
            }
        }

        return streamResponse.url
    }

    private func findLocalSong(for serverId: UUID) async throws -> LocalSong? {
        let descriptor = FetchDescriptor<LocalSong>(
            predicate: #Predicate { $0.serverSongId == serverId }
        )
        return try modelContext.fetch(descriptor).first
    }
}
```

---

#### 9.5 File System Watching

**Watch for Changes:**
```swift
class LocalLibraryWatcher {
    private var monitor: DispatchSourceFileSystemObject?
    private var directoryWatchers: [String: DispatchSourceFileSystemObject] = [:]

    func startWatching(path: URL) {
        let fd = open(path.path, O_EVTONLY)
        guard fd >= 0 else { return }

        monitor = DispatchSource.makeFileSystemObjectSource(
            fileDescriptor: fd,
            eventMask: [.write, .rename, .delete],
            queue: .global()
        )

        monitor?.setEventHandler { [weak self] in
            self?.handleFileSystemChange()
        }

        monitor?.setCancelHandler {
            close(fd)
        }

        monitor?.resume()
    }

    private func handleFileSystemChange() {
        // Debounce and rescan
        DispatchQueue.main.asyncAfter(deadline: .now() + 1.0) {
            Task {
                await LocalLibraryManager.shared.incrementalScan()
            }
        }
    }

    func stopWatching() {
        monitor?.cancel()
        monitor = nil
    }
}
```

---

### Phase 10: Portable Audio Player Sync

Sync playlists and songs to external devices like USB drives, portable audio players (DAPs), or SD cards. Supports any device that mounts as a volume and plays standard audio files.

#### 10.1 Device Detection

**Detect Mounted Volumes:**
```swift
class ExternalDeviceManager {
    func getExternalDevices() -> [ExternalDevice] {
        let volumes = FileManager.default.mountedVolumeURLs(
            includingResourceValuesForKeys: [
                .volumeNameKey,
                .volumeTotalCapacityKey,
                .volumeAvailableCapacityKey,
                .volumeIsRemovableKey,
                .volumeIsEjectableKey
            ],
            options: [.skipHiddenVolumes]
        ) ?? []

        return volumes.compactMap { url -> ExternalDevice? in
            guard let resources = try? url.resourceValues(forKeys: [
                .volumeNameKey,
                .volumeTotalCapacityKey,
                .volumeAvailableCapacityKey,
                .volumeIsRemovableKey,
                .volumeIsEjectableKey
            ]) else { return nil }

            // Only include removable/ejectable volumes (USB drives, SD cards)
            guard resources.volumeIsRemovable == true ||
                  resources.volumeIsEjectable == true else { return nil }

            return ExternalDevice(
                path: url,
                name: resources.volumeName ?? url.lastPathComponent,
                totalCapacity: resources.volumeTotalCapacity ?? 0,
                availableCapacity: resources.volumeAvailableCapacity ?? 0
            )
        }
    }
}

struct ExternalDevice: Identifiable {
    let path: URL
    let name: String
    let totalCapacity: Int
    let availableCapacity: Int

    var id: String { path.path }

    var formattedAvailable: String {
        ByteCountFormatter.string(fromByteCount: Int64(availableCapacity), countStyle: .file)
    }

    var formattedTotal: String {
        ByteCountFormatter.string(fromByteCount: Int64(totalCapacity), countStyle: .file)
    }
}
```

---

#### 10.2 Sync Configuration

**Sync Settings Per Device:**
```swift
struct DeviceSyncConfig: Codable, Identifiable {
    var id: String { deviceIdentifier }

    let deviceIdentifier: String           // Volume UUID or name
    var musicFolderPath: String = "Music"  // Relative path on device
    var playlistsToSync: [Int] = []        // Playlist IDs to sync
    var syncFavorites: Bool = true
    var syncRecentlyPlayed: Bool = false
    var maxSongs: Int? = nil               // Limit number of songs
    var maxSizeGB: Double? = nil           // Limit total size
    var transcodeFormat: TranscodeFormat = .original
    var organizationStyle: OrganizationStyle = .artistAlbum
    var lastSyncDate: Date? = nil
}

enum TranscodeFormat: String, Codable, CaseIterable {
    case original = "Original"
    case mp3_320 = "MP3 320kbps"
    case mp3_256 = "MP3 256kbps"
    case mp3_192 = "MP3 192kbps"
    case mp3_128 = "MP3 128kbps"
    case aac_256 = "AAC 256kbps"
}

enum OrganizationStyle: String, Codable, CaseIterable {
    case flat = "All songs in one folder"
    case artistAlbum = "Artist/Album/Song"
    case albumArtist = "Album - Artist/Song"
    case playlist = "Playlist/Song"
}
```

---

#### 10.3 Sync Engine

**Sync Process:**
```swift
class DeviceSyncEngine {
    enum SyncProgress {
        case preparing
        case analyzing(current: Int, total: Int)
        case downloading(song: String, current: Int, total: Int)
        case copying(song: String, current: Int, total: Int)
        case transcoding(song: String, current: Int, total: Int)
        case writingPlaylists
        case cleaningUp
        case complete(stats: SyncStats)
        case failed(Error)
    }

    struct SyncStats {
        var songsAdded: Int
        var songsRemoved: Int
        var songsSkipped: Int
        var bytesTransferred: Int64
        var duration: TimeInterval
    }

    @Published var progress: SyncProgress = .preparing

    func sync(config: DeviceSyncConfig, device: ExternalDevice) async throws {
        progress = .preparing

        // 1. Build list of songs to sync
        let songsToSync = try await buildSyncList(config: config)
        progress = .analyzing(current: 0, total: songsToSync.count)

        // 2. Get existing files on device
        let devicePath = device.path.appendingPathComponent(config.musicFolderPath)
        try FileManager.default.createDirectory(at: devicePath, withIntermediateDirectories: true)
        let existingFiles = try scanExistingFiles(at: devicePath)

        // 3. Determine what to add/remove
        let (toAdd, toRemove, toSkip) = diffSyncList(
            desired: songsToSync,
            existing: existingFiles,
            config: config
        )

        // 4. Remove old files
        for file in toRemove {
            try FileManager.default.removeItem(at: file)
        }

        // 5. Copy/transcode new files
        for (index, song) in toAdd.enumerated() {
            progress = .copying(song: song.title, current: index + 1, total: toAdd.count)

            let destinationPath = buildDestinationPath(
                song: song,
                basePath: devicePath,
                style: config.organizationStyle
            )

            try await copySongToDevice(
                song: song,
                destination: destinationPath,
                transcodeFormat: config.transcodeFormat
            )
        }

        // 6. Write M3U playlists
        progress = .writingPlaylists
        try await writePlaylistFiles(
            config: config,
            songs: songsToSync,
            basePath: devicePath
        )

        // 7. Done
        progress = .complete(stats: SyncStats(
            songsAdded: toAdd.count,
            songsRemoved: toRemove.count,
            songsSkipped: toSkip.count,
            bytesTransferred: 0, // Calculate actual
            duration: 0 // Calculate actual
        ))
    }

    private func buildSyncList(config: DeviceSyncConfig) async throws -> [Song] {
        var songs: [Song] = []

        // Add playlist songs
        for playlistId in config.playlistsToSync {
            let playlistSongs = try await APIClient.shared.getPlaylistSongs(playlistId: playlistId)
            songs.append(contentsOf: playlistSongs)
        }

        // Add favorites
        if config.syncFavorites {
            let favorites = try await APIClient.shared.getFavorites(type: "song")
            songs.append(contentsOf: favorites)
        }

        // Remove duplicates
        var seen = Set<UUID>()
        songs = songs.filter { seen.insert($0.id).inserted }

        // Apply limits
        if let maxSongs = config.maxSongs {
            songs = Array(songs.prefix(maxSongs))
        }

        return songs
    }

    private func buildDestinationPath(song: Song, basePath: URL, style: OrganizationStyle) -> URL {
        let sanitize: (String) -> String = { name in
            // Remove invalid filename characters
            let invalidChars = CharacterSet(charactersIn: ":/\\?*\"<>|")
            return name.components(separatedBy: invalidChars).joined()
        }

        let filename = "\(sanitize(song.title)).\(song.audioFormat.fileExtension)"

        switch style {
        case .flat:
            return basePath.appendingPathComponent(filename)
        case .artistAlbum:
            return basePath
                .appendingPathComponent(sanitize(song.artistName))
                .appendingPathComponent(sanitize(song.albumName))
                .appendingPathComponent(filename)
        case .albumArtist:
            return basePath
                .appendingPathComponent("\(sanitize(song.albumName)) - \(sanitize(song.artistName))")
                .appendingPathComponent(filename)
        case .playlist:
            // Handled separately per playlist
            return basePath.appendingPathComponent(filename)
        }
    }
}
```

---

#### 10.4 Transcoding

**Transcode for Device Compatibility:**
```swift
import AVFoundation

class AudioTranscoder {
    func transcode(
        source: URL,
        destination: URL,
        format: TranscodeFormat
    ) async throws {
        guard format != .original else {
            // Just copy
            try FileManager.default.copyItem(at: source, to: destination)
            return
        }

        let asset = AVAsset(url: source)

        // Create export session
        guard let exportSession = AVAssetExportSession(
            asset: asset,
            presetName: getExportPreset(for: format)
        ) else {
            throw TranscodeError.unsupportedFormat
        }

        exportSession.outputURL = destination
        exportSession.outputFileType = getOutputFileType(for: format)
        exportSession.audioMix = nil

        await exportSession.export()

        if let error = exportSession.error {
            throw error
        }
    }

    private func getExportPreset(for format: TranscodeFormat) -> String {
        switch format {
        case .original: return AVAssetExportPresetPassthrough
        case .mp3_320, .mp3_256, .mp3_192, .mp3_128:
            return AVAssetExportPresetAppleM4A // macOS doesn't have direct MP3 export
        case .aac_256:
            return AVAssetExportPresetAppleM4A
        }
    }

    private func getOutputFileType(for format: TranscodeFormat) -> AVFileType {
        switch format {
        case .mp3_320, .mp3_256, .mp3_192, .mp3_128:
            return .mp3
        case .aac_256:
            return .m4a
        case .original:
            return .m4a
        }
    }
}

enum TranscodeError: Error {
    case unsupportedFormat
    case exportFailed
}
```

**Alternative: Use FFmpeg for better MP3 support:**
```swift
class FFmpegTranscoder {
    func transcode(source: URL, destination: URL, format: TranscodeFormat) async throws {
        let bitrate = getBitrate(for: format)
        let codec = getCodec(for: format)

        // Shell out to ffmpeg (must be installed)
        let process = Process()
        process.executableURL = URL(fileURLWithPath: "/opt/homebrew/bin/ffmpeg")
        process.arguments = [
            "-i", source.path,
            "-codec:a", codec,
            "-b:a", bitrate,
            "-y", // Overwrite
            destination.path
        ]

        try process.run()
        process.waitUntilExit()

        if process.terminationStatus != 0 {
            throw TranscodeError.exportFailed
        }
    }

    private func getBitrate(for format: TranscodeFormat) -> String {
        switch format {
        case .mp3_320: return "320k"
        case .mp3_256: return "256k"
        case .mp3_192: return "192k"
        case .mp3_128: return "128k"
        case .aac_256: return "256k"
        case .original: return "320k"
        }
    }

    private func getCodec(for format: TranscodeFormat) -> String {
        switch format {
        case .mp3_320, .mp3_256, .mp3_192, .mp3_128:
            return "libmp3lame"
        case .aac_256:
            return "aac"
        case .original:
            return "copy"
        }
    }
}
```

---

#### 10.5 Playlist File Generation

**Generate M3U Playlists:**
```swift
class PlaylistFileWriter {
    func writeM3U(
        name: String,
        songs: [Song],
        basePath: URL,
        relativePaths: [UUID: String]
    ) throws {
        var content = "#EXTM3U\n"
        content += "#PLAYLIST:\(name)\n\n"

        for song in songs {
            guard let relativePath = relativePaths[song.id] else { continue }

            // Extended info line
            content += "#EXTINF:\(song.length),\(song.artistName) - \(song.title)\n"
            // File path (use forward slashes for compatibility)
            content += "\(relativePath.replacingOccurrences(of: "\\", with: "/"))\n"
        }

        let playlistPath = basePath.appendingPathComponent("\(name).m3u")
        try content.write(to: playlistPath, atomically: true, encoding: .utf8)
    }

    func writePLS(name: String, songs: [Song], basePath: URL, relativePaths: [UUID: String]) throws {
        var content = "[playlist]\n"

        for (index, song) in songs.enumerated() {
            guard let relativePath = relativePaths[song.id] else { continue }
            let num = index + 1
            content += "File\(num)=\(relativePath)\n"
            content += "Title\(num)=\(song.artistName) - \(song.title)\n"
            content += "Length\(num)=\(song.length)\n"
        }

        content += "NumberOfEntries=\(songs.count)\n"
        content += "Version=2\n"

        let playlistPath = basePath.appendingPathComponent("\(name).pls")
        try content.write(to: playlistPath, atomically: true, encoding: .utf8)
    }
}
```

---

#### 10.6 Sync UI

**Sync View:**
```swift
struct DeviceSyncView: View {
    @StateObject private var viewModel = DeviceSyncViewModel()
    @State private var selectedDevice: ExternalDevice?
    @State private var showingConfigSheet = false

    var body: some View {
        HSplitView {
            // Device list
            List(viewModel.devices, selection: $selectedDevice) { device in
                DeviceRow(device: device)
            }
            .frame(minWidth: 200)

            // Sync configuration
            if let device = selectedDevice {
                DeviceSyncConfigView(
                    device: device,
                    config: viewModel.getConfig(for: device)
                )
            } else {
                ContentUnavailableView(
                    "No Device Selected",
                    systemImage: "externaldrive",
                    description: Text("Connect a portable audio player or USB drive")
                )
            }
        }
        .toolbar {
            ToolbarItem {
                Button("Refresh") {
                    viewModel.refreshDevices()
                }
            }
            ToolbarItem {
                Button("Sync Now") {
                    Task {
                        await viewModel.startSync()
                    }
                }
                .disabled(selectedDevice == nil)
            }
        }
        .onAppear {
            viewModel.refreshDevices()
        }
    }
}

struct DeviceSyncConfigView: View {
    let device: ExternalDevice
    @Binding var config: DeviceSyncConfig

    var body: some View {
        Form {
            Section("Device Info") {
                LabeledContent("Name", value: device.name)
                LabeledContent("Available", value: device.formattedAvailable)
                LabeledContent("Total", value: device.formattedTotal)
            }

            Section("What to Sync") {
                PlaylistPicker(selected: $config.playlistsToSync)
                Toggle("Sync Favorites", isOn: $config.syncFavorites)
                Toggle("Sync Recently Played", isOn: $config.syncRecentlyPlayed)
            }

            Section("Options") {
                Picker("Audio Format", selection: $config.transcodeFormat) {
                    ForEach(TranscodeFormat.allCases, id: \.self) { format in
                        Text(format.rawValue).tag(format)
                    }
                }

                Picker("Organization", selection: $config.organizationStyle) {
                    ForEach(OrganizationStyle.allCases, id: \.self) { style in
                        Text(style.rawValue).tag(style)
                    }
                }

                TextField("Music Folder", text: $config.musicFolderPath)
            }

            Section("Limits") {
                OptionalIntField("Max Songs", value: $config.maxSongs)
                OptionalDoubleField("Max Size (GB)", value: $config.maxSizeGB)
            }

            if let lastSync = config.lastSyncDate {
                Section {
                    LabeledContent("Last Synced", value: lastSync.formatted())
                }
            }
        }
        .formStyle(.grouped)
        .padding()
    }
}
```

---

## Data Models (Swift)

```swift
struct Song: Codable, Identifiable {
    let id: UUID
    let title: String
    let artistId: UUID
    let artistName: String
    let artistSlug: String
    let albumId: UUID
    let albumName: String
    let albumSlug: String
    let albumCover: String?
    let length: Int
    let track: Int?
    let disc: Int?
    let year: Int?
    let genre: String?
    let audioFormat: AudioFormat
    let isFavorite: Bool
    let playCount: Int
    let lyrics: String?
    let smartFolder: SmartFolderBasic?
    let tags: [TagBasic]
    let createdAt: Date
    let lastPlayedAt: Date?

    enum CodingKeys: String, CodingKey {
        case id, title, length, track, disc, year, genre, lyrics, tags
        case artistId = "artist_id"
        case artistName = "artist_name"
        case artistSlug = "artist_slug"
        case albumId = "album_id"
        case albumName = "album_name"
        case albumSlug = "album_slug"
        case albumCover = "album_cover"
        case audioFormat = "audio_format"
        case isFavorite = "is_favorite"
        case playCount = "play_count"
        case smartFolder = "smart_folder"
        case createdAt = "created_at"
        case lastPlayedAt = "last_played_at"
    }

    var albumCoverURL: URL? {
        guard let cover = albumCover else { return nil }
        return URL(string: cover)
    }
}

enum AudioFormat: String, Codable {
    case mp3 = "MP3"
    case aac = "AAC"
    case flac = "FLAC"
}

struct Album: Codable, Identifiable {
    let id: UUID
    let name: String
    let artistId: UUID
    let artistName: String
    let cover: String?
    let year: Int?
    let songCount: Int
    let totalLength: Int
    let createdAt: Date

    enum CodingKeys: String, CodingKey {
        case id, name, cover, year
        case artistId = "artist_id"
        case artistName = "artist_name"
        case songCount = "song_count"
        case totalLength = "total_length"
        case createdAt = "created_at"
    }
}

struct Artist: Codable, Identifiable {
    let id: UUID
    let name: String
    let image: String?
    let bio: String?
    let albumCount: Int
    let songCount: Int
    let musicbrainzId: String?
    let createdAt: Date

    enum CodingKeys: String, CodingKey {
        case id, name, image, bio
        case albumCount = "album_count"
        case songCount = "song_count"
        case musicbrainzId = "musicbrainz_id"
        case createdAt = "created_at"
    }
}

struct Playlist: Codable, Identifiable {
    let id: Int
    let name: String
    let slug: String
    let description: String?
    let cover: String?
    let isSmart: Bool
    let rules: [SmartPlaylistRule]?
    let songCount: Int
    let totalLength: Int
    let createdAt: Date
    let updatedAt: Date

    enum CodingKeys: String, CodingKey {
        case id, name, slug, description, cover, rules
        case isSmart = "is_smart"
        case songCount = "song_count"
        case totalLength = "total_length"
        case createdAt = "created_at"
        case updatedAt = "updated_at"
    }
}

struct Tag: Codable, Identifiable {
    let id: Int
    let name: String
    let slug: String
    let color: String
    let songCount: Int
    let parentId: Int?
    let depth: Int
    let isSpecial: Bool
    let autoAssignPattern: String?
    let children: [Tag]?
    let createdAt: Date

    enum CodingKeys: String, CodingKey {
        case id, name, slug, color, depth, children
        case songCount = "song_count"
        case parentId = "parent_id"
        case isSpecial = "is_special"
        case autoAssignPattern = "auto_assign_pattern"
        case createdAt = "created_at"
    }
}

struct SmartFolder: Codable, Identifiable {
    let id: Int
    let name: String
    let path: String
    let depth: Int
    let songCount: Int
    let parentId: Int?
    let children: [SmartFolder]?

    enum CodingKeys: String, CodingKey {
        case id, name, path, depth, children
        case songCount = "song_count"
        case parentId = "parent_id"
    }
}

struct Device: Codable, Identifiable {
    let deviceId: String
    let name: String
    let type: DeviceType
    let isPlaying: Bool
    let currentSong: Song?
    let position: Double
    let volume: Double
    let lastSeen: Date

    var id: String { deviceId }

    enum CodingKeys: String, CodingKey {
        case name, type, position, volume
        case deviceId = "device_id"
        case isPlaying = "is_playing"
        case currentSong = "current_song"
        case lastSeen = "last_seen"
    }
}

enum DeviceType: String, Codable {
    case web
    case mobile
    case desktop
}
```

---

## API Response Wrappers

```swift
struct APIResponse<T: Codable>: Codable {
    let data: T
}

struct PaginatedResponse<T: Codable>: Codable {
    let data: [T]
    let links: PaginationLinks
    let meta: PaginationMeta
}

struct PaginationLinks: Codable {
    let first: String?
    let last: String?
    let prev: String?
    let next: String?
}

struct PaginationMeta: Codable {
    let currentPage: Int
    let from: Int?
    let lastPage: Int
    let perPage: Int
    let to: Int?
    let total: Int

    enum CodingKeys: String, CodingKey {
        case from, to, total
        case currentPage = "current_page"
        case lastPage = "last_page"
        case perPage = "per_page"
    }
}

struct ValidationError: Codable, Error {
    let message: String
    let errors: [String: [String]]
}
```

---

## Error Handling

| HTTP Code | Meaning | macOS Action |
|-----------|---------|------------|
| 400 | Business logic error | Show alert dialog |
| 401 | Unauthorized | Show login sheet |
| 403 | Forbidden | Show permission alert |
| 404 | Not found | Remove from local cache |
| 415 | Bad file type | Show file type error |
| 422 | Validation error | Show field errors |
| 429 | Rate limited | Retry with backoff |
| 500 | Server error | Show generic error alert |

---

## Summary: Complete API Endpoint Reference

### Authentication
| Endpoint | Method | Auth | Description |
|----------|--------|------|-------------|
| `/auth/login` | POST | No | Login |
| `/auth/logout` | DELETE | Yes | Logout |
| `/auth/me` | GET | Yes | Get profile |
| `/auth/me` | PATCH | Yes | Update profile |

### Songs
| Endpoint | Method | Auth | Description |
|----------|--------|------|-------------|
| `/songs` | GET | Yes | List songs |
| `/songs/recently-played` | GET | Yes | Recent history |
| `/songs/{id}` | GET | Yes | Get song |
| `/songs/{id}` | PATCH | Admin | Update metadata |
| `/songs/{id}/stream` | GET | Yes | Get stream URL |
| `/songs/{id}/download` | GET | Yes | Download file |
| `/songs/{id}/tags` | POST | Yes | Add tags |
| `/songs/{id}/tags` | DELETE | Yes | Remove tags |

### Albums
| Endpoint | Method | Auth | Description |
|----------|--------|------|-------------|
| `/albums` | GET | Yes | List albums |
| `/albums/{id}` | GET | Yes | Get album |
| `/albums/{id}/songs` | GET | Yes | Album songs |

### Artists
| Endpoint | Method | Auth | Description |
|----------|--------|------|-------------|
| `/artists` | GET | Yes | List artists |
| `/artists/{id}` | GET | Yes | Get artist |
| `/artists/{id}/albums` | GET | Yes | Artist albums |
| `/artists/{id}/songs` | GET | Yes | Artist songs |

### Playlists
| Endpoint | Method | Auth | Description |
|----------|--------|------|-------------|
| `/playlists` | GET | Yes | List playlists |
| `/playlists` | POST | Yes | Create playlist |
| `/playlists/{id}` | GET | Yes | Get playlist |
| `/playlists/{id}` | PATCH | Yes | Update playlist |
| `/playlists/{id}` | DELETE | Yes | Delete playlist |
| `/playlists/{id}/songs` | GET | Yes | Playlist songs |
| `/playlists/{id}/songs` | POST | Yes | Add songs |
| `/playlists/{id}/songs` | DELETE | Yes | Remove songs |
| `/playlists/{id}/songs/reorder` | PUT | Yes | Reorder songs |

### Smart Folders
| Endpoint | Method | Auth | Description |
|----------|--------|------|-------------|
| `/smart-folders` | GET | Yes | List folders |
| `/smart-folders/{id}/songs` | GET | Yes | Folder songs |

### Tags
| Endpoint | Method | Auth | Description |
|----------|--------|------|-------------|
| `/tags` | GET | Yes | List tags |
| `/tags` | POST | Yes | Create tag |
| `/tags/{id}` | GET | Yes | Get tag |
| `/tags/{id}` | PATCH | Yes | Update tag |
| `/tags/{id}` | DELETE | Yes | Delete tag |
| `/tags/{id}/songs` | GET | Yes | Tag songs |

### Favorites
| Endpoint | Method | Auth | Description |
|----------|--------|------|-------------|
| `/favorites` | GET | Yes | List favorites |
| `/favorites` | POST | Yes | Add favorite |
| `/favorites` | DELETE | Yes | Remove favorite |

### Interactions
| Endpoint | Method | Auth | Description |
|----------|--------|------|-------------|
| `/interactions/play` | POST | Yes | Record play |

### Search
| Endpoint | Method | Auth | Description |
|----------|--------|------|-------------|
| `/search` | GET | Yes | Full-text search |

### Upload
| Endpoint | Method | Auth | Description |
|----------|--------|------|-------------|
| `/upload` | POST | Yes | Upload file |

### Player/Devices
| Endpoint | Method | Auth | Description |
|----------|--------|------|-------------|
| `/player/devices` | GET | Yes | List devices |
| `/player/devices` | POST | Yes | Register device |
| `/player/devices/{id}` | DELETE | Yes | Unregister |
| `/player/control` | POST | Yes | Remote control |
| `/player/state` | GET | Yes | Playback state |
| `/player/sync` | POST | Yes | Sync queue |

### Admin
| Endpoint | Method | Auth | Description |
|----------|--------|------|-------------|
| `/admin/users` | GET | Admin | List users |
| `/admin/users` | POST | Admin | Create user |
| `/admin/users/{id}` | GET | Admin | Get user |
| `/admin/users/{id}` | PATCH | Admin | Update user |
| `/admin/users/{id}` | DELETE | Admin | Delete user |
| `/admin/library/scan` | POST | Admin | Start scan |
| `/admin/library/scan/status` | GET | Admin | Scan status |
| `/admin/metadata/enrich` | POST | Admin | Enrich metadata |

---

## Recommended Libraries

| Purpose | Library | Notes |
|---------|---------|-------|
| Networking | URLSession | Native, sufficient |
| JSON | Codable | Native |
| Images | Kingfisher or SDWebImage | Async loading + caching |
| Database | SwiftData | Local caching (macOS 14+) |
| Real-time | PusherSwift | Remote control events |
| Keychain | KeychainAccess | Token storage |
| Audio | AVFoundation | Native player |

---

## App Architecture

### Recommended Structure

```
Muzakily/
├── App/
│   ├── MuzakilyApp.swift          # App entry point
│   ├── AppDelegate.swift          # AppKit delegate for Dock menu, etc.
│   └── Commands.swift             # Menu bar commands
├── Models/
│   ├── Song.swift
│   ├── Album.swift
│   ├── Artist.swift
│   ├── Playlist.swift
│   └── ...
├── Views/
│   ├── Sidebar/
│   │   ├── SidebarView.swift
│   │   └── SidebarItem.swift
│   ├── Library/
│   │   ├── SongsView.swift
│   │   ├── AlbumsView.swift
│   │   └── ArtistsView.swift
│   ├── Player/
│   │   ├── NowPlayingView.swift
│   │   ├── MiniPlayerView.swift
│   │   └── QueueView.swift
│   ├── Playlists/
│   │   ├── PlaylistsView.swift
│   │   └── PlaylistDetailView.swift
│   └── Settings/
│       └── SettingsView.swift
├── ViewModels/
│   ├── LibraryViewModel.swift
│   ├── PlayerViewModel.swift
│   └── SearchViewModel.swift
├── Services/
│   ├── APIClient.swift
│   ├── PlayerManager.swift
│   ├── DownloadManager.swift
│   └── AuthManager.swift
├── Utilities/
│   ├── KeychainHelper.swift
│   ├── ImageCache.swift
│   └── Extensions.swift
└── Resources/
    ├── Assets.xcassets
    └── Info.plist
```

### SwiftUI Navigation

```swift
struct ContentView: View {
    @State private var selectedSection: SidebarSection? = .songs
    @StateObject private var playerManager = PlayerManager.shared

    var body: some View {
        NavigationSplitView {
            SidebarView(selection: $selectedSection)
        } detail: {
            switch selectedSection {
            case .songs:
                SongsView()
            case .albums:
                AlbumsView()
            case .artists:
                ArtistsView()
            case .playlists:
                PlaylistsView()
            case .playlist(let id):
                PlaylistDetailView(playlistId: id)
            case .smartFolder(let id):
                SmartFolderView(folderId: id)
            case .tag(let id):
                TagView(tagId: id)
            case .favorites:
                FavoritesView()
            case .recentlyPlayed:
                RecentlyPlayedView()
            case nil:
                Text("Select a section")
            }
        }
        .toolbar {
            ToolbarItemGroup(placement: .primaryAction) {
                MiniPlayerToolbar()
            }
        }
        .searchable(text: $searchText, prompt: "Search")
    }
}

enum SidebarSection: Hashable {
    case songs
    case albums
    case artists
    case playlists
    case playlist(Int)
    case smartFolder(Int)
    case tag(Int)
    case favorites
    case recentlyPlayed
}
```

---

## macOS Version Requirements

- **Minimum:** macOS 14.0 (Sonoma) - for SwiftData
- **Alternative:** macOS 13.0 (Ventura) - if using Core Data instead

### Why macOS 14+
- SwiftData for modern data persistence
- Improved SwiftUI APIs
- Better menu bar extra support
- Enhanced Observation framework

---

## Distribution

### Mac App Store
- Sandboxed environment
- Automatic updates
- App Review required
- Requires Apple Developer Program membership

### Direct Distribution (Outside App Store)
- Notarization required
- Can use hardened runtime without sandbox
- Self-managed updates (Sparkle framework)
- More flexibility with system integrations

### Recommended: Both
- Mac App Store for discovery and trust
- Direct download for users who prefer it
- Use Sparkle for direct distribution updates

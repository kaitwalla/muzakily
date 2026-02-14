# iOS App Implementation Plan

This document outlines the plan for implementing Muzakily features in a native iOS app. The iOS app will integrate with an existing codebase and communicate with the Muzakily backend via the REST API.

## Overview

The iOS app will provide a native music streaming experience with all web features plus iOS-specific capabilities like background audio, CarPlay, and offline downloads.

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

**iOS Implementation Notes:**
- Store token in Keychain using `Security` framework
- Use `URLSession` with custom `URLSessionConfiguration` for auth headers
- Handle 401 responses globally to prompt re-authentication

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

**iOS Implementation Notes:**
- Use `Codable` for JSON parsing
- Implement `UICollectionViewDiffableDataSource` for song lists
- Cache song metadata in Core Data for offline access
- Use `NSFetchedResultsController` for sorted/filtered views

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

**iOS Implementation Notes:**
- Use `SDWebImage` or `Kingfisher` for async image loading
- Implement album grid with `UICollectionViewCompositionalLayout`
- Cache cover art aggressively (they don't change)

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

**iOS Implementation Notes:**
- Use `AVPlayer` for streaming playback
- Presigned URLs expire - fetch new URL if playback fails with 403
- Implement `AVAudioSession` for background audio:

```swift
do {
    try AVAudioSession.sharedInstance().setCategory(
        .playback,
        mode: .default,
        options: [.allowAirPlay, .allowBluetooth]
    )
    try AVAudioSession.sharedInstance().setActive(true)
} catch {
    print("Audio session error: \(error)")
}
```

- Handle interruptions (phone calls, Siri, etc.)
- Update `MPNowPlayingInfoCenter` for lock screen info
- Respond to `MPRemoteCommandCenter` for lock screen controls

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

**iOS Implementation Notes:**
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

**iOS Implementation Notes:**
- Smart playlists cannot have songs manually added/removed
- Display a rule summary in the UI
- Consider a visual rule builder UI

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

**iOS Implementation Notes:**
- Show heart icon on songs, allow quick toggle
- Sync favorites state with server
- Cache locally for offline access

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

**iOS Implementation Notes:**
- Use `UISearchController` for search UI
- Implement debouncing (300ms) before API calls
- Show recent searches locally
- Display results in sectioned list

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
  "device_id": "ios-abc123",        // Unique ID (use identifierForVendor)
  "name": "John's iPhone",          // User-friendly name
  "type": "mobile"                  // web, mobile, desktop
}
```

**iOS Implementation Notes:**
- Use `UIDevice.current.identifierForVendor` for device ID
- Register on app launch
- Include device name from `UIDevice.current.name`
- Update `last_seen` periodically

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

**iOS Implementation Notes:**
- Implement Pusher client for real-time updates
- Subscribe to user's private channel for commands
- Update UI when receiving remote commands
- Allow device picker in Now Playing view

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

**iOS Implementation Notes:**
- Use `URLSession` with `uploadTask`
- Support background uploads
- Show progress in UI
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

## iOS-Specific Features

### Background Audio

```swift
// Info.plist
<key>UIBackgroundModes</key>
<array>
    <string>audio</string>
</array>
```

```swift
// Configure audio session
try AVAudioSession.sharedInstance().setCategory(
    .playback,
    mode: .default,
    options: [.allowAirPlay, .allowBluetooth, .allowBluetoothA2DP]
)
```

### Now Playing Info

```swift
var nowPlayingInfo = [String: Any]()
nowPlayingInfo[MPMediaItemPropertyTitle] = song.title
nowPlayingInfo[MPMediaItemPropertyArtist] = song.artistName
nowPlayingInfo[MPMediaItemPropertyAlbumTitle] = song.albumName
nowPlayingInfo[MPMediaItemPropertyPlaybackDuration] = song.length
nowPlayingInfo[MPNowPlayingInfoPropertyElapsedPlaybackTime] = currentTime
nowPlayingInfo[MPNowPlayingInfoPropertyPlaybackRate] = isPlaying ? 1.0 : 0.0

if let artwork = cachedArtwork {
    nowPlayingInfo[MPMediaItemPropertyArtwork] = MPMediaItemArtwork(boundsSize: artwork.size) { _ in artwork }
}

MPNowPlayingInfoCenter.default().nowPlayingInfo = nowPlayingInfo
```

### Remote Command Center

```swift
let commandCenter = MPRemoteCommandCenter.shared()

commandCenter.playCommand.addTarget { _ in
    self.play()
    return .success
}

commandCenter.pauseCommand.addTarget { _ in
    self.pause()
    return .success
}

commandCenter.nextTrackCommand.addTarget { _ in
    self.next()
    return .success
}

commandCenter.previousTrackCommand.addTarget { _ in
    self.previous()
    return .success
}

commandCenter.changePlaybackPositionCommand.addTarget { event in
    guard let event = event as? MPChangePlaybackPositionCommandEvent else { return .commandFailed }
    self.seek(to: event.positionTime)
    return .success
}
```

### CarPlay Support

```swift
// Info.plist
<key>UIBackgroundModes</key>
<array>
    <string>audio</string>
</array>

// Scene configuration for CarPlay
<key>UIApplicationSceneManifest</key>
<dict>
    <key>UISceneConfigurations</key>
    <dict>
        <key>CPTemplateApplicationSceneSessionRoleApplication</key>
        <array>
            <dict>
                <key>UISceneClassName</key>
                <string>CPTemplateApplicationScene</string>
                <key>UISceneConfigurationName</key>
                <string>CarPlay</string>
                <key>UISceneDelegateClassName</key>
                <string>$(PRODUCT_MODULE_NAME).CarPlaySceneDelegate</string>
            </dict>
        </array>
    </dict>
</dict>
```

Implement `CPTemplateApplicationSceneDelegate`:
- `CPTabBarTemplate` for main navigation
- `CPListTemplate` for browsing songs/playlists
- `CPNowPlayingTemplate` for playback

### Offline Downloads

**Local Storage Structure:**
```
Documents/
  downloads/
    {song_id}/
      audio.mp3
      metadata.json
      artwork.jpg
```

**Core Data Model:**
```swift
@Entity
class DownloadedSong {
    @Attribute var id: UUID
    @Attribute var title: String
    @Attribute var artistName: String
    @Attribute var albumName: String
    @Attribute var localPath: String
    @Attribute var downloadedAt: Date
    @Attribute var fileSize: Int64
}
```

**Download Manager:**
- Use `URLSession` background download tasks
- Resume interrupted downloads
- Track download progress
- Delete downloads when storage is low

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

| HTTP Code | Meaning | iOS Action |
|-----------|---------|------------|
| 400 | Business logic error | Show error message |
| 401 | Unauthorized | Redirect to login |
| 403 | Forbidden | Show permission error |
| 404 | Not found | Remove from local cache |
| 415 | Bad file type | Show file type error |
| 422 | Validation error | Show field errors |
| 429 | Rate limited | Retry with backoff |
| 500 | Server error | Show generic error |

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
| Database | Core Data or SwiftData | Local caching |
| Real-time | PusherSwift | Remote control events |
| Keychain | KeychainAccess | Token storage |
| Audio | AVFoundation | Native player |

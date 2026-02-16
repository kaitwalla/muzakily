# Songs API

Endpoints for browsing, streaming, and managing songs.

## List Songs

```
GET /api/v1/songs
```

Returns a paginated list of songs with optional filtering and sorting.

## Recently Played

```
GET /api/v1/songs/recently-played
```

Returns songs the current user has recently played, ordered by last played time (most recent first).

### Query Parameters

| Parameter | Type | Default | Description |
|-----------|------|---------|-------------|
| `limit` | integer | 10 | Number of results (max 50) |

### Example Request

```bash
curl "https://api.example.com/api/v1/songs/recently-played?limit=10" \
  -H "Authorization: Bearer {token}"
```

### Example Response

```json
{
  "data": [
    {
      "id": "550e8400-e29b-41d4-a716-446655440000",
      "title": "Love Song",
      "artist_name": "The Artist",
      "album_name": "Greatest Hits",
      "album_cover": "https://cdn.example.com/covers/album.jpg",
      "length": 245,
      "is_favorite": true,
      "play_count": 42,
      "last_played_at": "2024-01-20T15:30:00.000000Z"
    }
  ],
  "links": { ... },
  "meta": { ... }
}
```

### Query Parameters

| Parameter | Type | Default | Description |
|-----------|------|---------|-------------|
| `search` | string | - | Search in title, artist, album |
| `artist_id` | uuid | - | Filter by artist |
| `album_id` | uuid | - | Filter by album |
| `genre` | string | - | Filter by genre |
| `smart_folder_id` | integer | - | Filter by smart folder |
| `format` | string | - | Filter by format (MP3, AAC, FLAC) |
| `favorited` | boolean | - | Show only favorited songs |
| `incomplete` | boolean | - | Show only songs with missing metadata |
| `updated_since` | ISO 8601 | - | Filter songs updated since timestamp (for incremental sync) |
| `sort` | string | title | Sort field: title, artist_name, album_name, year, created_at |
| `order` | string | asc | Sort order: asc, desc |
| `per_page` | integer | 50 | Results per page (max 100) |

### Example Request

```bash
curl "https://api.example.com/api/v1/songs?search=love&sort=year&order=desc&per_page=20" \
  -H "Authorization: Bearer {token}"
```

### Example Response

```json
{
  "data": [
    {
      "id": "550e8400-e29b-41d4-a716-446655440000",
      "title": "Love Song",
      "artist_id": "660e8400-e29b-41d4-a716-446655440001",
      "artist_name": "The Artist",
      "artist_slug": "660e8400-e29b-41d4-a716-446655440001",
      "album_id": "770e8400-e29b-41d4-a716-446655440002",
      "album_name": "Greatest Hits",
      "album_slug": "770e8400-e29b-41d4-a716-446655440002",
      "album_cover": "https://cdn.example.com/covers/album.jpg",
      "length": 245,
      "track": 3,
      "disc": 1,
      "year": 2023,
      "genre": "Pop",
      "audio_format": "FLAC",
      "is_favorite": true,
      "play_count": 42,
      "smart_folder": {
        "id": 5,
        "name": "Pop Music",
        "path": "Music/Pop"
      },
      "tags": [
        {"id": 1, "name": "Favorites", "slug": "favorites", "color": "#ff0000"}
      ],
      "created_at": "2024-01-15T10:30:00.000000Z"
    }
  ],
  "links": {
    "first": "https://api.example.com/api/v1/songs?page=1",
    "last": "https://api.example.com/api/v1/songs?page=10",
    "prev": null,
    "next": "https://api.example.com/api/v1/songs?page=2"
  },
  "meta": {
    "current_page": 1,
    "from": 1,
    "last_page": 10,
    "per_page": 50,
    "to": 50,
    "total": 500
  }
}
```

## Get Song

```
GET /api/v1/songs/{id}
```

Returns details for a single song.

### Example Response

```json
{
  "data": {
    "id": "550e8400-e29b-41d4-a716-446655440000",
    "title": "Love Song",
    "artist_id": "660e8400-e29b-41d4-a716-446655440001",
    "artist_name": "The Artist",
    "artist_slug": "660e8400-e29b-41d4-a716-446655440001",
    "album_id": "770e8400-e29b-41d4-a716-446655440002",
    "album_name": "Greatest Hits",
    "album_slug": "770e8400-e29b-41d4-a716-446655440002",
    "album_cover": "https://cdn.example.com/covers/album.jpg",
    "length": 245,
    "track": 3,
    "disc": 1,
    "year": 2023,
    "genre": "Pop",
    "audio_format": "FLAC",
    "is_favorite": true,
    "play_count": 42,
    "lyrics": "These are the lyrics...",
    "smart_folder": {
      "id": 5,
      "name": "Pop Music",
      "path": "Music/Pop"
    },
    "tags": [
      {"id": 1, "name": "Favorites", "slug": "favorites", "color": "#ff0000"}
    ],
    "created_at": "2024-01-15T10:30:00.000000Z"
  }
}
```

## Update Song (Admin Only)

```
PATCH /api/v1/songs/{id}
```

Update song metadata. Requires admin role.

### Request Body

| Field | Type | Description |
|-------|------|-------------|
| `title` | string | Song title |
| `artist_name` | string | Artist name (may create new artist) |
| `album_name` | string | Album name (may create new album) |
| `year` | integer | Release year |
| `track` | integer | Track number |
| `disc` | integer | Disc number |
| `genre` | string | Genre |
| `lyrics` | string | Song lyrics |

### Example Request

```bash
curl -X PATCH "https://api.example.com/api/v1/songs/{id}" \
  -H "Authorization: Bearer {token}" \
  -H "Content-Type: application/json" \
  -d '{"title": "Updated Title", "year": 2024}'
```

## Bulk Update Songs (Admin Only)

```
PUT /api/v1/songs/bulk
```

Update metadata and tags for multiple songs at once. Requires admin role.

### Request Body

| Field | Type | Required | Description |
|-------|------|----------|-------------|
| `song_ids` | array | Yes | UUIDs of songs to update |
| `title` | string | No | Set title for all songs |
| `artist_name` | string | No | Set artist for all songs |
| `album_name` | string | No | Set album for all songs |
| `year` | integer | No | Set year for all songs |
| `track` | integer | No | Set track number for all songs |
| `disc` | integer | No | Set disc number for all songs |
| `genre` | string | No | Set genre for all songs |
| `add_tag_ids` | array | No | Tag IDs to add to all songs |
| `remove_tag_ids` | array | No | Tag IDs to remove from all songs |

### Example Request

```bash
curl -X PUT "https://api.example.com/api/v1/songs/bulk" \
  -H "Authorization: Bearer {token}" \
  -H "Content-Type: application/json" \
  -d '{
    "song_ids": [
      "550e8400-e29b-41d4-a716-446655440000",
      "550e8400-e29b-41d4-a716-446655440001"
    ],
    "genre": "Rock",
    "add_tag_ids": [1, 2],
    "remove_tag_ids": [3]
  }'
```

### Example Response

```json
{
  "data": [
    {
      "id": "550e8400-e29b-41d4-a716-446655440000",
      "title": "Song One",
      "genre": "Rock",
      "tags": [
        {"id": 1, "name": "Favorites", "slug": "favorites"},
        {"id": 2, "name": "Rock", "slug": "rock"}
      ]
    },
    {
      "id": "550e8400-e29b-41d4-a716-446655440001",
      "title": "Song Two",
      "genre": "Rock",
      "tags": [
        {"id": 1, "name": "Favorites", "slug": "favorites"},
        {"id": 2, "name": "Rock", "slug": "rock"}
      ]
    }
  ]
}
```

## Stream Song

```
GET /api/v1/songs/{id}/stream
```

Get a presigned streaming URL for the song.

### Query Parameters

| Parameter | Type | Default | Description |
|-----------|------|---------|-------------|
| `format` | string | original | Desired format (mp3, aac, or original) |
| `bitrate` | integer | 256 | Desired bitrate in kbps |

### Example Response

```json
{
  "data": {
    "url": "https://r2.example.com/songs/abc123?signature=xyz...",
    "audio_format": "MP3",
    "audio_length": 245
  }
}
```

The URL is a presigned URL valid for a limited time. Use it directly in an audio player.

## Download Song

```
GET /api/v1/songs/{id}/download
```

Download the original song file. Returns a 302 redirect to a presigned download URL.

## Add Tags to Song

```
POST /api/v1/songs/{id}/tags
```

Add one or more tags to a song.

### Request Body

```json
{
  "tag_ids": [1, 2, 3]
}
```

### Example Response

```json
{
  "data": {
    "song_id": "550e8400-e29b-41d4-a716-446655440000",
    "tags": [
      {"id": 1, "name": "Rock", "slug": "rock"},
      {"id": 2, "name": "Favorites", "slug": "favorites"},
      {"id": 3, "name": "Workout", "slug": "workout"}
    ]
  }
}
```

## Remove Tags from Song

```
DELETE /api/v1/songs/{id}/tags
```

Remove tags from a song.

### Request Body

```json
{
  "tag_ids": [2, 3]
}
```

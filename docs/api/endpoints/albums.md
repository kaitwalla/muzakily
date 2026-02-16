# Albums API

Endpoints for browsing albums and their songs.

## List Albums

```
GET /api/v1/albums
```

Returns a paginated list of albums.

### Query Parameters

| Parameter | Type | Default | Description |
|-----------|------|---------|-------------|
| `search` | string | - | Search in album name |
| `artist_id` | uuid | - | Filter by artist |
| `year` | integer | - | Filter by release year |
| `sort` | string | name | Sort field: name, year, created_at |
| `order` | string | asc | Sort order: asc, desc |
| `per_page` | integer | 50 | Results per page (max 100) |

### Example Request

```bash
curl "https://api.example.com/api/v1/albums?year=2023&sort=name" \
  -H "Authorization: Bearer {token}"
```

### Example Response

```json
{
  "data": [
    {
      "id": "770e8400-e29b-41d4-a716-446655440002",
      "name": "Greatest Hits",
      "artist_id": "660e8400-e29b-41d4-a716-446655440001",
      "artist_name": "The Artist",
      "cover": "https://cdn.example.com/covers/album.jpg",
      "year": 2023,
      "song_count": 12,
      "total_length": 2940,
      "created_at": "2024-01-15T10:30:00.000000Z"
    }
  ],
  "links": {...},
  "meta": {...}
}
```

## Get Album

```
GET /api/v1/albums/{id}
```

Returns details for a single album.

### Example Response

```json
{
  "data": {
    "id": "770e8400-e29b-41d4-a716-446655440002",
    "name": "Greatest Hits",
    "artist_id": "660e8400-e29b-41d4-a716-446655440001",
    "artist_name": "The Artist",
    "cover": "https://cdn.example.com/covers/album.jpg",
    "year": 2023,
    "song_count": 12,
    "total_length": 2940,
    "created_at": "2024-01-15T10:30:00.000000Z"
  }
}
```

## Get Album Songs

```
GET /api/v1/albums/{id}/songs
```

Returns all songs in the album, ordered by disc number then track number.

### Example Response

```json
{
  "data": [
    {
      "id": "550e8400-e29b-41d4-a716-446655440000",
      "title": "Opening Track",
      "artist_name": "The Artist",
      "album_name": "Greatest Hits",
      "length": 245,
      "track": 1,
      "disc": 1,
      "year": 2023,
      "genre": "Pop",
      "audio_format": "FLAC",
      "is_favorite": false,
      "play_count": 10,
      "created_at": "2024-01-15T10:30:00.000000Z"
    },
    {
      "id": "550e8400-e29b-41d4-a716-446655440001",
      "title": "Second Track",
      "track": 2,
      "disc": 1,
      ...
    }
  ]
}
```

## Usage Examples

### Get all albums by an artist

```bash
curl "https://api.example.com/api/v1/albums?artist_id=660e8400-e29b-41d4-a716-446655440001" \
  -H "Authorization: Bearer {token}"
```

### Get albums from a specific year, sorted by name

```bash
curl "https://api.example.com/api/v1/albums?year=2023&sort=name&order=asc" \
  -H "Authorization: Bearer {token}"
```

### Play an entire album

1. Get album songs: `GET /api/v1/albums/{id}/songs`
2. Add all songs to the player queue
3. For each song, get streaming URL: `GET /api/v1/songs/{song_id}/stream`

## Upload Album Cover

```
POST /api/v1/albums/{id}/cover
```

Upload a custom cover image for an album.

### Request

```bash
curl -X POST "https://api.example.com/api/v1/albums/{id}/cover" \
  -H "Authorization: Bearer {token}" \
  -F "cover=@/path/to/cover.jpg"
```

### Response

```json
{
  "data": {
    "id": "770e8400-e29b-41d4-a716-446655440002",
    "name": "Greatest Hits",
    "cover": "https://cdn.example.com/covers/album-new.jpg",
    ...
  }
}
```

### Supported Formats

- JPEG
- PNG
- WebP

## Refresh Album Cover

```
POST /api/v1/albums/{id}/refresh-cover
```

Fetch cover art from external sources (MusicBrainz, Discogs, etc.).

### Request

```bash
curl -X POST "https://api.example.com/api/v1/albums/{id}/refresh-cover" \
  -H "Authorization: Bearer {token}"
```

### Response

```json
{
  "data": {
    "id": "770e8400-e29b-41d4-a716-446655440002",
    "name": "Greatest Hits",
    "cover": "https://cdn.example.com/covers/album-refreshed.jpg",
    ...
  }
}
```

### Error: Cover Not Found

```json
{
  "error": {
    "code": "COVER_FETCH_FAILED",
    "message": "Could not find or download cover art for this album"
  }
}
```

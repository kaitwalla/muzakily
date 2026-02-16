# Artists API

Endpoints for browsing artists and their content.

## List Artists

```
GET /api/v1/artists
```

Returns a paginated list of artists.

### Query Parameters

| Parameter | Type | Default | Description |
|-----------|------|---------|-------------|
| `search` | string | - | Search in artist name |
| `updated_since` | ISO 8601 | - | Filter artists updated since timestamp (for incremental sync) |
| `sort` | string | name | Sort field: name, created_at |
| `order` | string | asc | Sort order: asc, desc |
| `per_page` | integer | 50 | Results per page (max 100) |

### Example Request

```bash
curl "https://api.example.com/api/v1/artists?search=beatles" \
  -H "Authorization: Bearer {token}"
```

### Example Response

```json
{
  "data": [
    {
      "id": "660e8400-e29b-41d4-a716-446655440001",
      "name": "The Beatles",
      "image": "https://cdn.example.com/artists/beatles.jpg",
      "album_count": 13,
      "song_count": 213,
      "created_at": "2024-01-15T10:30:00.000000Z"
    }
  ],
  "links": {...},
  "meta": {...}
}
```

## Get Artist

```
GET /api/v1/artists/{id}
```

Returns detailed information for a single artist, including bio and MusicBrainz ID.

### Example Response

```json
{
  "data": {
    "id": "660e8400-e29b-41d4-a716-446655440001",
    "name": "The Beatles",
    "image": "https://cdn.example.com/artists/beatles.jpg",
    "bio": "The Beatles were an English rock band formed in Liverpool in 1960...",
    "album_count": 13,
    "song_count": 213,
    "musicbrainz_id": "b10bbbfc-cf9e-42e0-be17-e2c3e1d2600d",
    "created_at": "2024-01-15T10:30:00.000000Z"
  }
}
```

## Get Artist Albums

```
GET /api/v1/artists/{id}/albums
```

Returns all albums by the artist, ordered by year (descending) then name.

### Example Response

```json
{
  "data": [
    {
      "id": "770e8400-e29b-41d4-a716-446655440010",
      "name": "Abbey Road",
      "artist_id": "660e8400-e29b-41d4-a716-446655440001",
      "artist_name": "The Beatles",
      "cover": "https://cdn.example.com/covers/abbey-road.jpg",
      "year": 1969,
      "song_count": 17,
      "total_length": 2820,
      "created_at": "2024-01-15T10:30:00.000000Z"
    },
    {
      "id": "770e8400-e29b-41d4-a716-446655440011",
      "name": "Let It Be",
      "year": 1970,
      ...
    }
  ]
}
```

## Get Artist Songs

```
GET /api/v1/artists/{id}/songs
```

Returns a paginated list of all songs by the artist.

### Query Parameters

| Parameter | Type | Default | Description |
|-----------|------|---------|-------------|
| `sort` | string | album_name | Sort field: album_name, title |
| `order` | string | asc | Sort order: asc, desc |
| `per_page` | integer | 50 | Results per page (max 100) |

### Example Request

```bash
curl "https://api.example.com/api/v1/artists/{id}/songs?sort=title&per_page=100" \
  -H "Authorization: Bearer {token}"
```

### Example Response

```json
{
  "data": [
    {
      "id": "550e8400-e29b-41d4-a716-446655440000",
      "title": "Come Together",
      "artist_name": "The Beatles",
      "album_name": "Abbey Road",
      "length": 259,
      "track": 1,
      "disc": 1,
      "year": 1969,
      "genre": "Rock",
      "audio_format": "FLAC",
      "is_favorite": true,
      "play_count": 150,
      "created_at": "2024-01-15T10:30:00.000000Z"
    }
  ],
  "links": {...},
  "meta": {...}
}
```

## Usage Examples

### Browse an artist's discography

```bash
# Get artist details
curl "https://api.example.com/api/v1/artists/{id}" \
  -H "Authorization: Bearer {token}"

# Get all their albums
curl "https://api.example.com/api/v1/artists/{id}/albums" \
  -H "Authorization: Bearer {token}"

# Get songs from a specific album
curl "https://api.example.com/api/v1/albums/{album_id}/songs" \
  -H "Authorization: Bearer {token}"
```

### Shuffle all songs by an artist

1. Fetch all songs: `GET /api/v1/artists/{id}/songs?per_page=100`
2. Shuffle the array client-side
3. Add to player queue

# Playlists API

Endpoints for managing regular and smart playlists.

## List Playlists

```
GET /api/v1/playlists
```

Returns all playlists owned by the authenticated user. This endpoint returns a flat list (not paginated) since users typically have a manageable number of playlists.

### Query Parameters

| Parameter | Type | Default | Description |
|-----------|------|---------|-------------|
| `updated_since` | ISO 8601 | - | Filter playlists updated since timestamp (for incremental sync) |

### Example Response

```json
{
  "data": [
    {
      "id": 1,
      "name": "My Favorites",
      "slug": "my-favorites",
      "description": "Songs I love",
      "cover": "https://cdn.example.com/playlists/1.jpg",
      "is_smart": false,
      "rules": null,
      "song_count": 25,
      "total_length": 5400,
      "created_at": "2024-01-15T10:30:00.000000Z",
      "updated_at": "2024-01-20T15:45:00.000000Z"
    },
    {
      "id": 2,
      "name": "Recently Added Rock",
      "slug": "recently-added-rock",
      "description": "Rock songs from the last 30 days",
      "is_smart": true,
      "rules": [
        {
          "logic": "and",
          "rules": [
            {"field": "genre", "operator": "equals", "value": "Rock"},
            {"field": "created_at", "operator": "in_last", "value": "30 days"}
          ]
        }
      ],
      "song_count": 15,
      "total_length": 3200
    }
  ]
}
```

## Create Playlist

```
POST /api/v1/playlists
```

Create a new regular or smart playlist.

### Request Body

| Field | Type | Required | Description |
|-------|------|----------|-------------|
| `name` | string | Yes | Playlist name (max 255 chars) |
| `description` | string | No | Description (max 1000 chars) |
| `is_smart` | boolean | No | Whether this is a smart playlist |
| `rules` | array | If smart | Smart playlist rules |
| `song_ids` | array | No | Initial songs (regular playlists only) |

### Regular Playlist Example

```bash
curl -X POST "https://api.example.com/api/v1/playlists" \
  -H "Authorization: Bearer {token}" \
  -H "Content-Type: application/json" \
  -d '{
    "name": "Road Trip",
    "description": "Songs for the highway",
    "song_ids": [
      "550e8400-e29b-41d4-a716-446655440000",
      "550e8400-e29b-41d4-a716-446655440001"
    ]
  }'
```

### Smart Playlist Example

```bash
curl -X POST "https://api.example.com/api/v1/playlists" \
  -H "Authorization: Bearer {token}" \
  -H "Content-Type: application/json" \
  -d '{
    "name": "80s Favorites",
    "is_smart": true,
    "rules": [
      {
        "logic": "and",
        "rules": [
          {"field": "year", "operator": "between", "value": [1980, 1989]},
          {"field": "is_favorite", "operator": "equals", "value": true}
        ]
      }
    ]
  }'
```

### Smart Playlist Rule Fields

| Field | Operators | Value Type |
|-------|-----------|------------|
| `title` | contains, equals, starts_with, ends_with | string |
| `artist_name` | contains, equals, starts_with, ends_with | string |
| `album_name` | contains, equals, starts_with, ends_with | string |
| `genre` | equals, contains | string |
| `year` | equals, greater_than, less_than, between | integer |
| `play_count` | equals, greater_than, less_than | integer |
| `is_favorite` | equals | boolean |
| `audio_format` | equals | MP3, AAC, FLAC |
| `smart_folder_id` | equals | integer |
| `tag` | has, has_any | integer or array |
| `created_at` | before, after, in_last | date or duration |
| `length` | greater_than, less_than, between | seconds |

## Get Playlist

```
GET /api/v1/playlists/{id}
```

Returns playlist details. Uses slug or ID.

## Update Playlist

```
PATCH /api/v1/playlists/{id}
```

Update playlist metadata or rules.

```bash
curl -X PATCH "https://api.example.com/api/v1/playlists/1" \
  -H "Authorization: Bearer {token}" \
  -H "Content-Type: application/json" \
  -d '{"name": "Updated Name", "description": "New description"}'
```

## Delete Playlist

```
DELETE /api/v1/playlists/{id}
```

Permanently delete a playlist.

## Get Playlist Songs

```
GET /api/v1/playlists/{id}/songs
```

Returns songs in the playlist. For smart playlists, rules are evaluated dynamically.

### Example Response

```json
{
  "data": [
    {
      "id": "550e8400-e29b-41d4-a716-446655440000",
      "title": "Song Title",
      "artist_name": "Artist",
      "album_name": "Album",
      "length": 245,
      ...
    }
  ]
}
```

## Add Songs to Playlist

```
POST /api/v1/playlists/{id}/songs
```

Add songs to a regular playlist. Cannot be used on smart playlists.

### Request Body

| Field | Type | Required | Description |
|-------|------|----------|-------------|
| `song_ids` | array | Yes | UUIDs of songs to add |
| `position` | integer | No | Insert position (appends if omitted) |

```bash
curl -X POST "https://api.example.com/api/v1/playlists/1/songs" \
  -H "Authorization: Bearer {token}" \
  -H "Content-Type: application/json" \
  -d '{
    "song_ids": ["550e8400-e29b-41d4-a716-446655440000"],
    "position": 0
  }'
```

### Error: Smart Playlist

```json
{
  "error": {
    "code": "INVALID_OPERATION",
    "message": "Cannot add songs to a smart playlist"
  }
}
```

## Remove Songs from Playlist

```
DELETE /api/v1/playlists/{id}/songs
```

Remove songs from a regular playlist.

```bash
curl -X DELETE "https://api.example.com/api/v1/playlists/1/songs" \
  -H "Authorization: Bearer {token}" \
  -H "Content-Type: application/json" \
  -d '{"song_ids": ["550e8400-e29b-41d4-a716-446655440000"]}'
```

## Reorder Playlist Songs

```
PUT /api/v1/playlists/{id}/songs/reorder
```

Set the order of songs in a regular playlist.

```bash
curl -X PUT "https://api.example.com/api/v1/playlists/1/songs/reorder" \
  -H "Authorization: Bearer {token}" \
  -H "Content-Type: application/json" \
  -d '{
    "song_ids": [
      "550e8400-e29b-41d4-a716-446655440002",
      "550e8400-e29b-41d4-a716-446655440000",
      "550e8400-e29b-41d4-a716-446655440001"
    ]
  }'
```

The `song_ids` array must contain all songs currently in the playlist in the desired order.

## Upload Playlist Cover

```
POST /api/v1/playlists/{id}/cover
```

Upload a custom cover image for a playlist.

### Request

```bash
curl -X POST "https://api.example.com/api/v1/playlists/{id}/cover" \
  -H "Authorization: Bearer {token}" \
  -F "cover=@/path/to/cover.jpg"
```

### Response

```json
{
  "data": {
    "id": 1,
    "name": "My Favorites",
    "cover": "https://cdn.example.com/playlists/1-custom.jpg",
    ...
  }
}
```

### Supported Formats

- JPEG
- PNG
- WebP

## Refresh Playlist Cover (Smart Playlists Only)

```
POST /api/v1/playlists/{id}/refresh-cover
```

Generate a new cover image for smart playlists based on the album covers of matching songs.

### Request

```bash
curl -X POST "https://api.example.com/api/v1/playlists/{id}/refresh-cover" \
  -H "Authorization: Bearer {token}"
```

### Response

```json
{
  "data": {
    "id": 2,
    "name": "Recently Added Rock",
    "is_smart": true,
    "cover": "https://cdn.example.com/playlists/2-generated.jpg",
    ...
  }
}
```

### Error: Not a Smart Playlist

```json
{
  "error": {
    "code": "INVALID_OPERATION",
    "message": "Cover refresh is only available for smart playlists"
  }
}
```

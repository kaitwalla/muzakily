# Sync API

Endpoints for incremental synchronization, enabling mobile clients to efficiently sync library changes without full re-downloads.

## Overview

The sync API provides two mechanisms for incremental sync:

1. **`updated_since` parameter** - Available on list endpoints (`/songs`, `/albums`, `/artists`, `/playlists`) to fetch only items modified after a given timestamp.

2. **Deleted items endpoint** - Returns IDs of items that have been deleted since a given timestamp, allowing clients to remove them from local storage.

## Sync Status

```
GET /api/v1/sync/status
```

Returns current library counts and last updated timestamps. Use this to determine if a sync is needed.

### Example Request

```bash
curl "https://api.example.com/api/v1/sync/status" \
  -H "Authorization: Bearer {token}"
```

### Example Response

```json
{
  "data": {
    "songs": {
      "count": 5432,
      "last_updated": "2024-01-20T15:30:00+00:00"
    },
    "albums": {
      "count": 312,
      "last_updated": "2024-01-20T14:00:00+00:00"
    },
    "artists": {
      "count": 198,
      "last_updated": "2024-01-19T10:00:00+00:00"
    },
    "playlists": {
      "count": 24,
      "last_updated": "2024-01-20T16:45:00+00:00"
    },
    "library_updated_at": "2024-01-20T16:45:00+00:00"
  }
}
```

### Response Fields

| Field | Description |
|-------|-------------|
| `songs.count` | Total number of songs in the library |
| `songs.last_updated` | Timestamp of the most recently updated song |
| `albums.count` | Total number of albums |
| `albums.last_updated` | Timestamp of the most recently updated album |
| `artists.count` | Total number of artists |
| `artists.last_updated` | Timestamp of the most recently updated artist |
| `playlists.count` | Number of playlists owned by the authenticated user |
| `playlists.last_updated` | Timestamp of the user's most recently updated playlist |
| `library_updated_at` | Overall library last updated timestamp (max of all entities) |

## Get Deleted Items

```
GET /api/v1/deleted
```

Returns IDs of items that have been deleted since a given timestamp. Use this to remove items from local storage during incremental sync.

### Query Parameters

| Parameter | Type | Required | Description |
|-----------|------|----------|-------------|
| `since` | ISO 8601 | Yes | Return items deleted after this timestamp |

### Example Request

```bash
curl "https://api.example.com/api/v1/deleted?since=2024-01-15T00:00:00Z" \
  -H "Authorization: Bearer {token}"
```

### Example Response

```json
{
  "data": {
    "songs": [
      "550e8400-e29b-41d4-a716-446655440000",
      "550e8400-e29b-41d4-a716-446655440001"
    ],
    "albums": [
      "770e8400-e29b-41d4-a716-446655440002"
    ],
    "artists": [],
    "playlists": [
      "880e8400-e29b-41d4-a716-446655440003"
    ]
  },
  "meta": {
    "since": "2024-01-15T00:00:00+00:00",
    "queried_at": "2024-01-20T16:45:00+00:00"
  }
}
```

### Response Fields

| Field | Description |
|-------|-------------|
| `data.songs` | Array of deleted song UUIDs |
| `data.albums` | Array of deleted album UUIDs |
| `data.artists` | Array of deleted artist UUIDs |
| `data.playlists` | Array of deleted playlist UUIDs (only the user's own playlists) |
| `meta.since` | The `since` timestamp from the request |
| `meta.queried_at` | Server timestamp when the query was executed |

### Notes

- Playlists are user-scoped: only returns playlists that belonged to the authenticated user
- Songs, albums, and artists are shared across all users
- Deleted item records are retained for a limited time (e.g., 30 days) before being pruned

## Incremental Sync Pattern

Use the `updated_since` parameter on list endpoints to fetch only changed items.

### Example: Sync Songs

```bash
# Fetch songs updated since last sync
curl "https://api.example.com/api/v1/songs?updated_since=2024-01-15T00:00:00Z&per_page=100" \
  -H "Authorization: Bearer {token}"
```

### Supported Endpoints

| Endpoint | Parameter |
|----------|-----------|
| `GET /api/v1/songs` | `updated_since` |
| `GET /api/v1/albums` | `updated_since` |
| `GET /api/v1/artists` | `updated_since` |
| `GET /api/v1/playlists` | `updated_since` |

### Date Format

The `updated_since` and `since` parameters accept ISO 8601 formatted timestamps:

- `2024-01-15T00:00:00Z` (UTC)
- `2024-01-15T00:00:00+00:00` (with timezone offset)
- `2024-01-15` (date only, interpreted as midnight UTC)

Invalid date formats will return a `422 Unprocessable Entity` error.

## Recommended Sync Flow

1. **Check sync status**
   ```
   GET /api/v1/sync/status
   ```
   Compare `library_updated_at` with your last sync timestamp.

2. **Fetch deleted items**
   ```
   GET /api/v1/deleted?since={last_sync_timestamp}
   ```
   Remove these IDs from local storage.

3. **Fetch updated items** (for each entity type)
   ```
   GET /api/v1/songs?updated_since={last_sync_timestamp}&per_page=100
   GET /api/v1/albums?updated_since={last_sync_timestamp}&per_page=100
   GET /api/v1/artists?updated_since={last_sync_timestamp}&per_page=100
   GET /api/v1/playlists?updated_since={last_sync_timestamp}
   ```
   Upsert these into local storage.

4. **Store new sync timestamp**
   Use `meta.queried_at` from the deleted endpoint response as your new last sync timestamp.

## Error Responses

### Missing `since` Parameter

```json
{
  "message": "The since field is required.",
  "errors": {
    "since": ["The since field is required."]
  }
}
```

### Invalid Date Format

```json
{
  "message": "Invalid date format for updated_since parameter"
}
```

Status: `422 Unprocessable Entity`

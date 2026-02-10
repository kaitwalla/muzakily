# Smart Folders API

Smart folders automatically organize songs based on their file system path in R2 storage. They provide a hierarchical view of your music library.

## List Smart Folders

```
GET /api/v1/smart-folders
```

Returns the smart folder hierarchy (top-level folders with nested children).

### Example Response

```json
{
  "data": [
    {
      "id": 1,
      "name": "Music",
      "path": "Music",
      "depth": 0,
      "song_count": 1500,
      "parent_id": null,
      "children": [
        {
          "id": 2,
          "name": "Rock",
          "path": "Music/Rock",
          "depth": 1,
          "song_count": 450,
          "parent_id": 1,
          "children": [
            {
              "id": 5,
              "name": "Classic Rock",
              "path": "Music/Rock/Classic Rock",
              "depth": 2,
              "song_count": 200,
              "parent_id": 2,
              "children": []
            }
          ]
        },
        {
          "id": 3,
          "name": "Pop",
          "path": "Music/Pop",
          "depth": 1,
          "song_count": 350,
          "parent_id": 1,
          "children": []
        },
        {
          "id": 4,
          "name": "Jazz",
          "path": "Music/Jazz",
          "depth": 1,
          "song_count": 280,
          "parent_id": 1,
          "children": []
        }
      ]
    }
  ]
}
```

## Get Smart Folder Songs

```
GET /api/v1/smart-folders/{id}/songs
```

Returns a paginated list of songs in the smart folder (including songs in subfolders).

### Query Parameters

| Parameter | Type | Default | Description |
|-----------|------|---------|-------------|
| `sort` | string | title | Sort field: title, artist_name, album_name, year |
| `order` | string | asc | Sort order: asc, desc |
| `per_page` | integer | 50 | Results per page (max 100) |

### Example Request

```bash
curl "https://api.example.com/api/v1/smart-folders/2/songs?sort=artist_name&per_page=25" \
  -H "Authorization: Bearer {token}"
```

### Example Response

```json
{
  "data": [
    {
      "id": "550e8400-e29b-41d4-a716-446655440000",
      "title": "Stairway to Heaven",
      "artist_name": "Led Zeppelin",
      "album_name": "Led Zeppelin IV",
      "length": 482,
      "track": 4,
      "year": 1971,
      "genre": "Rock",
      "audio_format": "FLAC",
      "is_favorite": true,
      "play_count": 89,
      "smart_folder": {
        "id": 5,
        "name": "Classic Rock",
        "path": "Music/Rock/Classic Rock"
      },
      "created_at": "2024-01-15T10:30:00.000000Z"
    }
  ],
  "links": {...},
  "meta": {...}
}
```

## How Smart Folders Work

Smart folders are automatically created during library scanning based on the directory structure in your R2 storage bucket:

1. **Automatic creation**: When a song is scanned, its path is parsed to create smart folders
2. **Hierarchical**: Folders maintain parent-child relationships matching the file system
3. **Song counts**: Each folder shows the total count including songs in subfolders
4. **Read-only**: Smart folders cannot be manually created or edited

### Example File Structure

```
Music/
├── Rock/
│   ├── Classic Rock/
│   │   ├── led-zeppelin-iv.flac
│   │   └── dark-side-of-the-moon.flac
│   └── Alternative/
│       └── nevermind.flac
├── Pop/
│   └── thriller.flac
└── Jazz/
    └── kind-of-blue.flac
```

This creates smart folders:
- Music (5 songs)
  - Rock (3 songs)
    - Classic Rock (2 songs)
    - Alternative (1 song)
  - Pop (1 song)
  - Jazz (1 song)

## Using Smart Folders in Playlists

Smart folders can be used as a filter in smart playlist rules:

```json
{
  "name": "All Rock Music",
  "is_smart": true,
  "rules": [
    {
      "logic": "and",
      "rules": [
        {"field": "smart_folder_id", "operator": "equals", "value": 2}
      ]
    }
  ]
}
```

This creates a dynamic playlist containing all songs in the "Rock" smart folder and its subfolders.

## Filtering Songs by Smart Folder

You can also filter the songs endpoint by smart folder:

```bash
curl "https://api.example.com/api/v1/songs?smart_folder_id=5" \
  -H "Authorization: Bearer {token}"
```

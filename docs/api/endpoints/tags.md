# Tags API

Tags provide a flexible way to organize songs with custom labels and colors. Tags support hierarchical organization and automatic assignment patterns.

## List Tags

```
GET /api/v1/tags
```

Returns tags in hierarchical format by default.

### Query Parameters

| Parameter | Type | Default | Description |
|-----------|------|---------|-------------|
| `flat` | boolean | false | Return flat list instead of hierarchy |

### Hierarchical Response

```json
{
  "data": [
    {
      "id": 1,
      "name": "Mood",
      "slug": "mood",
      "color": "#3498db",
      "song_count": 150,
      "parent_id": null,
      "children": [
        {
          "id": 2,
          "name": "Happy",
          "slug": "happy",
          "color": "#f1c40f",
          "song_count": 45,
          "parent_id": 1,
          "children": []
        },
        {
          "id": 3,
          "name": "Sad",
          "slug": "sad",
          "color": "#9b59b6",
          "song_count": 30,
          "parent_id": 1,
          "children": []
        }
      ],
      "created_at": "2024-01-15T10:30:00.000000Z"
    },
    {
      "id": 4,
      "name": "Workout",
      "slug": "workout",
      "color": "#e74c3c",
      "song_count": 80,
      "parent_id": null,
      "children": [],
      "created_at": "2024-01-15T10:30:00.000000Z"
    }
  ]
}
```

### Flat Response

```bash
curl "https://api.example.com/api/v1/tags?flat=true" \
  -H "Authorization: Bearer {token}"
```

```json
{
  "data": [
    {"id": 1, "name": "Mood", "slug": "mood", "color": "#3498db", "song_count": 150, "parent_id": null},
    {"id": 2, "name": "Happy", "slug": "happy", "color": "#f1c40f", "song_count": 45, "parent_id": 1},
    {"id": 3, "name": "Sad", "slug": "sad", "color": "#9b59b6", "song_count": 30, "parent_id": 1},
    {"id": 4, "name": "Workout", "slug": "workout", "color": "#e74c3c", "song_count": 80, "parent_id": null}
  ]
}
```

## Create Tag

```
POST /api/v1/tags
```

Create a new tag.

### Request Body

| Field | Type | Required | Description |
|-------|------|----------|-------------|
| `name` | string | Yes | Tag name |
| `color` | string | No | Hex color code (e.g., #ff0000) |
| `parent_id` | integer | No | Parent tag ID for hierarchy |
| `auto_assign_pattern` | string | No | Regex pattern for auto-tagging |

### Example Request

```bash
curl -X POST "https://api.example.com/api/v1/tags" \
  -H "Authorization: Bearer {token}" \
  -H "Content-Type: application/json" \
  -d '{
    "name": "Chill",
    "color": "#1abc9c",
    "parent_id": 1,
    "auto_assign_pattern": "(?i)(chill|relaxing|ambient)"
  }'
```

### Auto-Assignment Pattern

The `auto_assign_pattern` is a regular expression that matches against song titles. When a new song is scanned, it's automatically tagged if the pattern matches:

```json
{
  "name": "Christmas",
  "color": "#c0392b",
  "auto_assign_pattern": "(?i)(christmas|xmas|holiday|jingle)"
}
```

This would automatically tag any song with "Christmas", "Xmas", "Holiday", or "Jingle" in the title.

## Get Tag

```
GET /api/v1/tags/{id}
```

Returns detailed tag information.

### Example Response

```json
{
  "data": {
    "id": 2,
    "name": "Happy",
    "slug": "happy",
    "color": "#f1c40f",
    "song_count": 45,
    "parent_id": 1,
    "depth": 1,
    "is_special": false,
    "auto_assign_pattern": null,
    "created_at": "2024-01-15T10:30:00.000000Z"
  }
}
```

## Update Tag

```
PATCH /api/v1/tags/{id}
```

Update tag properties.

```bash
curl -X PATCH "https://api.example.com/api/v1/tags/2" \
  -H "Authorization: Bearer {token}" \
  -H "Content-Type: application/json" \
  -d '{"name": "Joyful", "color": "#f39c12"}'
```

### Circular Hierarchy Prevention

You cannot set a tag's parent to itself or any of its descendants:

```json
{
  "message": "A tag cannot be its own parent."
}
```

## Delete Tag

```
DELETE /api/v1/tags/{id}
```

Delete a tag. This removes the tag from all songs but does not delete the songs.

## Get Tagged Songs

```
GET /api/v1/tags/{id}/songs
```

Returns songs with this tag.

### Query Parameters

| Parameter | Type | Default | Description |
|-----------|------|---------|-------------|
| `include_children` | boolean | false | Include songs from child tags |
| `per_page` | integer | 50 | Results per page |

### Example: Include Child Tags

```bash
# Get songs tagged "Mood" including songs tagged "Happy" and "Sad"
curl "https://api.example.com/api/v1/tags/1/songs?include_children=true" \
  -H "Authorization: Bearer {token}"
```

## Tag Songs

See [Songs API - Add Tags](songs.md#add-tags-to-song) for adding tags to songs.

## Using Tags in Smart Playlists

Tags can be used in smart playlist rules:

```json
{
  "name": "Workout Mix",
  "is_smart": true,
  "rules": [
    {
      "logic": "or",
      "rules": [
        {"field": "tag", "operator": "has", "value": 4},
        {"field": "tag", "operator": "has_any", "value": [2, 5]}
      ]
    }
  ]
}
```

### Tag Operators

| Operator | Description |
|----------|-------------|
| `has` | Song has the specific tag |
| `has_any` | Song has any of the specified tags |

# Search API

Full-text search powered by Meilisearch with PostgreSQL fallback.

## Search

```
GET /api/v1/search
```

Search across songs, albums, and artists with optional filtering.

### Query Parameters

| Parameter | Type | Required | Description |
|-----------|------|----------|-------------|
| `q` | string | Yes | Search query (min 2 characters) |
| `type` | string | No | Limit to: song, album, artist |
| `limit` | integer | No | Results per type (default 10, max 50) |
| `filters[year]` | integer | No | Filter by year |
| `filters[tag]` | string | No | Filter by tag slug |
| `filters[genre]` | string | No | Filter by genre |
| `filters[format]` | string | No | Filter by format (MP3, AAC, FLAC) |
| `filters[artist_id]` | uuid | No | Filter by artist |
| `filters[album_id]` | uuid | No | Filter by album |

### Basic Search

```bash
curl "https://api.example.com/api/v1/search?q=love" \
  -H "Authorization: Bearer {token}"
```

### Example Response

```json
{
  "data": {
    "songs": {
      "data": [
        {
          "id": "550e8400-e29b-41d4-a716-446655440000",
          "title": "Love Song",
          "artist_name": "The Artist",
          "album_name": "Greatest Hits",
          "length": 245,
          "year": 2023,
          "genre": "Pop",
          "audio_format": "FLAC",
          "is_favorite": false,
          "play_count": 12
        },
        {
          "id": "550e8400-e29b-41d4-a716-446655440001",
          "title": "Crazy in Love",
          "artist_name": "Another Artist",
          ...
        }
      ],
      "total": 25
    },
    "albums": {
      "data": [
        {
          "id": "770e8400-e29b-41d4-a716-446655440000",
          "name": "Love Songs Collection",
          "artist_name": "Various Artists",
          "year": 2022,
          "song_count": 15
        }
      ],
      "total": 3
    },
    "artists": {
      "data": [
        {
          "id": "660e8400-e29b-41d4-a716-446655440000",
          "name": "Love and Rockets",
          "album_count": 5,
          "song_count": 48
        }
      ],
      "total": 1
    }
  },
  "meta": {}
}
```

### Search Specific Type

```bash
# Search only songs
curl "https://api.example.com/api/v1/search?q=love&type=song" \
  -H "Authorization: Bearer {token}"

# Search only artists
curl "https://api.example.com/api/v1/search?q=beatles&type=artist" \
  -H "Authorization: Bearer {token}"
```

### Filtered Search

```bash
# Search for rock songs from the 80s
curl "https://api.example.com/api/v1/search?q=rock&filters[year]=1985&filters[genre]=Rock" \
  -H "Authorization: Bearer {token}"

# Search for FLAC files only
curl "https://api.example.com/api/v1/search?q=symphony&filters[format]=FLAC" \
  -H "Authorization: Bearer {token}"

# Search within an artist's catalog
curl "https://api.example.com/api/v1/search?q=night&filters[artist_id]=660e8400-e29b-41d4-a716-446655440000" \
  -H "Authorization: Bearer {token}"
```

### Increase Result Limit

```bash
curl "https://api.example.com/api/v1/search?q=jazz&limit=25" \
  -H "Authorization: Bearer {token}"
```

## Search Features

### Typo Tolerance

Meilisearch provides typo-tolerant search:

- "beetles" finds "Beatles"
- "mozrt" finds "Mozart"
- "sympohny" finds "Symphony"

### Relevance Ranking

Results are ranked by relevance:

1. Exact matches
2. Prefix matches (query matches start of word)
3. Typo-corrected matches
4. Partial matches

### Field Weights

Some fields are weighted higher:

- Song title (highest)
- Artist name (high)
- Album name (medium)
- Genre (lower)

## PostgreSQL Fallback

If Meilisearch is unavailable, the API falls back to PostgreSQL full-text search:

- Uses `tsvector` for efficient text matching
- Case-insensitive with `ilike`
- Still provides good results but without typo tolerance

The fallback is automatic and transparent to clients.

## Performance Tips

1. **Use type filter** when you know what you're looking for
2. **Use filters** to narrow results before searching
3. **Limit results** with reasonable `limit` values
4. **Cache results** client-side for repeated searches

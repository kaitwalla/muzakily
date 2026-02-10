# Search

Find songs, albums, and artists quickly with Muzakily's search.

## Quick Search

### Opening Search

- Press `/` from anywhere
- Click the search icon in the header
- Press `Ctrl+K` or `Cmd+K`

### Searching

1. Type your query (minimum 2 characters)
2. Results appear instantly
3. Press Enter to go to results page
4. Press Escape to close

## Search Results

Results are grouped by type:

### Songs

Shows matching song titles with:
- Artist and album info
- Duration
- Play button

### Albums

Shows matching album names with:
- Artist name
- Cover art
- Year

### Artists

Shows matching artist names with:
- Image
- Album and song counts

## Typo Tolerance

Search is forgiving of typos:

| You type | Finds |
|----------|-------|
| beetles | Beatles |
| mozrat | Mozart |
| sympohny | Symphony |
| micheal jackson | Michael Jackson |

## Search Tips

### Partial Matches

Search matches partial words:

- "moon" finds "Dark Side of the Moon"
- "love" finds "Love Song", "Endless Love", "Lovely Day"

### Multiple Terms

Space-separated terms are AND-ed:

- "beatles abbey" finds songs by Beatles on Abbey Road
- "rock 1970" finds rock songs from 1970

### Exact Phrases

Use quotes for exact matching:

- `"Let It Be"` finds exact title
- `"Dark Side"` excludes "Darkside"

## Filtered Search

### Search Within Results

On the full results page:

1. Use filters to narrow results
2. Filter by year, genre, format, or tags
3. Results update instantly

### Available Filters

| Filter | Description |
|--------|-------------|
| Year | Specific release year |
| Genre | Music genre |
| Format | MP3, AAC, or FLAC |
| Tag | Songs with specific tag |
| Artist | Within artist's catalog |
| Album | Within album |

### Example Filtered Searches

**Find 80s rock:**
1. Search "rock"
2. Filter Year: 1980-1989

**Find high-quality jazz:**
1. Search "jazz"
2. Filter Format: FLAC

**Find tagged favorites:**
1. Search artist name
2. Filter Tag: Favorites

## Search Shortcuts

| Action | Shortcut |
|--------|----------|
| Open search | `/` or `Ctrl/Cmd+K` |
| Close search | `Escape` |
| Navigate results | `↑` `↓` |
| Open result | `Enter` |
| Play result | `Ctrl/Cmd+Enter` |

## Search from Player

While music is playing, search without disrupting playback:

1. Open search
2. Find new songs
3. Add to queue without stopping current song

## Recent Searches

### View History

Your recent searches are saved:

1. Click in the search box
2. Recent searches appear below
3. Click to repeat a search

### Clear History

1. Click "Clear recent searches"
2. Or clear individually with X

## Search Scope

### Global Search

The main search bar searches everything:
- Songs
- Albums
- Artists

### Scoped Search

Within specific views, search is scoped:

- **In Artists view**: Searches artist names only
- **In Albums view**: Searches album names only
- **In Playlists view**: Searches playlist names only

## No Results

If search returns no results:

1. Check spelling
2. Try fewer terms
3. Use partial words
4. Remove filters
5. Try alternative names (e.g., "The Beatles" vs "Beatles")

## Performance

Search is optimized for speed:

- Powered by Meilisearch for instant results
- Falls back to database search if needed
- Results typically appear in under 100ms

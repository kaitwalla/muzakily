# Smart Playlists

Smart playlists automatically include songs matching your defined rules. They update dynamically as your library changes.

## Creating a Smart Playlist

1. Click **+ New Playlist** in the sidebar
2. Toggle **Smart Playlist** on
3. Define your rules
4. Click **Create**

## Rule Builder

### Rule Structure

Each rule has three parts:

1. **Field** - What to match (title, artist, year, etc.)
2. **Operator** - How to match (equals, contains, greater than)
3. **Value** - What to match against

### Example Rules

| Field | Operator | Value | Matches |
|-------|----------|-------|---------|
| Artist | is | Beatles | Songs by The Beatles |
| Year | is between | 1980-1989 | 80s music |
| Genre | contains | Rock | Rock, Classic Rock, Alternative Rock |
| Play Count | is greater than | 10 | Frequently played songs |
| Is Favorite | is | - | Favorited songs |
| Tag | has | Workout | Songs tagged "Workout" |

## Available Fields

### Text Fields

| Field | Description |
|-------|-------------|
| Title | Song title |
| Artist | Artist name |
| Album | Album name |
| Genre | Music genre |

**Operators**: is, is not, contains, does not contain, begins with, ends with

### Numeric Fields

| Field | Description |
|-------|-------------|
| Year | Release year |
| Track | Track number |
| Length | Duration in seconds |
| Play Count | Times played |

**Operators**: is, is not, is greater than, is less than, is between

### Boolean Fields

| Field | Description |
|-------|-------------|
| Is Favorite | Favorited songs |

**Operators**: is (favorited), is not (not favorited)

### Tag Fields

| Field | Description |
|-------|-------------|
| Tag | Assigned tags |

**Operators**: has (song has tag), does not have (song doesn't have tag)

You can also use text-style operators for partial matching: contains, does not contain, begins with, ends with

### Date Fields

| Field | Description |
|-------|-------------|
| Date Added | When added to library |
| Last Played | When last played |

**Operators**: in the last (N days), not in the last (N days)

### Other Fields

| Field | Description |
|-------|-------------|
| Audio Format | File format (MP3, AAC, FLAC) |

## Combining Rules

### AND Logic

All conditions must match:

```
Genre is "Rock"
AND Year is between 1970-1979
AND Is Favorite is true
```

Result: Favorited rock songs from the 70s

### OR Logic

Any condition can match:

```
Artist is "Beatles"
OR Artist is "Rolling Stones"
OR Artist is "The Who"
```

Result: Songs by any of these artists

### Nested Groups

Combine AND and OR:

```
(Genre is "Rock" OR Genre is "Metal")
AND Year is greater than 2000
AND Play Count is greater than 5
```

Result: Popular rock/metal from the 2000s+

## Smart Playlist Examples

### Recently Added

```
Date Added: in the last 30 days
```

### High Quality Files

```
Audio Format: is FLAC
```

### Unplayed Songs

```
Play Count: is 0
```

### Long Songs

```
Length: is greater than 600
```
(Songs over 10 minutes)

### 90s Favorites

```
Year: is between 1990-1999
AND Is Favorite: is true
```

### By Genre

```
Genre: contains "Jazz"
AND Year: is greater than 1950
```

### Tagged Content

```
Tag: has "Workout"
OR Tag: has "Energy"
```

### Favorites with Tag

```
Is Favorite: is true
AND Tag: has "Christmas"
```

This creates a playlist of only your favorite Christmas songs.

## Editing Smart Playlists

1. Open the smart playlist
2. Click **Edit Rules**
3. Modify rules in the builder
4. Click **Save**

Songs update immediately based on new rules.

## Refreshing Smart Playlists

Smart playlists automatically update when:
- You edit the rules
- New songs are added to your library (checked hourly)
- Songs in your library are modified

To force an immediate refresh:

1. Open the smart playlist
2. Click **...** menu
3. Select **Refresh**

This is useful after bulk-adding new music to ensure all matching songs appear immediately.

## Limitations

Smart playlists are dynamic:

- **Cannot manually add songs** - Songs are determined by rules
- **Cannot reorder songs** - Order is determined by sorting
- **Cannot remove specific songs** - Unfavorite or change metadata instead

## Sorting Smart Playlists

Set default sort order:

1. Open the smart playlist
2. Click the sort dropdown
3. Choose: Title, Artist, Album, Year, Play Count, Date Added
4. Choose: Ascending or Descending

## Converting to Regular Playlist

To "freeze" a smart playlist:

1. Open the smart playlist
2. Click **...** menu
3. Select **Convert to Regular Playlist**
4. A new regular playlist is created with current songs

The smart playlist remains unchanged.

## Performance Tips

- More specific rules = faster evaluation
- Avoid broad "contains" on large libraries
- Use indexed fields (Year, Format) for best performance

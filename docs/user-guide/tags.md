# Tags

Tags provide flexible, cross-cutting organization for your music library. Unlike folders, a song can have multiple tags.

## Viewing Tags

### Tag List

1. Click **Tags** in the sidebar
2. View all tags in a hierarchical or flat list
3. Click a tag to see tagged songs

### Tag Colors

Each tag has a color for visual identification. Colors appear:
- In tag lists
- On song cards
- In the tag filter

## Creating Tags

### From the Tag Manager

1. Go to **Tags** in the sidebar
2. Click **+ New Tag**
3. Enter a name
4. Choose a color
5. Optionally select a parent tag
6. Click **Create**

### Quick Create

When tagging a song:

1. Right-click a song → **Add Tags**
2. Type a new tag name
3. Press Enter to create and apply

## Tagging Songs

### Single Song

1. Right-click a song
2. Select **Add Tags**
3. Check tags to apply
4. Click **Save**

### Multiple Songs

1. Select multiple songs (Ctrl/Cmd + Click)
2. Right-click → **Add Tags**
3. Select tags
4. Tags are applied to all selected songs

### From Album/Artist View

1. Right-click an album or artist
2. Select **Add Tags**
3. Tags are applied to all songs

## Removing Tags

### From a Song

1. Right-click the song
2. Select **Edit Tags**
3. Uncheck tags to remove
4. Click **Save**

### Bulk Remove

1. Select multiple songs
2. Right-click → **Edit Tags**
3. Uncheck tags
4. Click **Save**

## Hierarchical Tags

Tags can have parent-child relationships:

```
Mood
├── Happy
├── Sad
├── Energetic
└── Relaxing

Activity
├── Workout
├── Study
├── Cooking
└── Driving
```

### Benefits

- **Organization**: Group related tags
- **Inheritance**: Search parent tag to find all children
- **Navigation**: Expand/collapse in tag list

### Creating Nested Tags

1. Create the parent tag first
2. Create child tag
3. Set parent in the dropdown

## Auto-Assignment

Tags can automatically apply to new songs based on patterns.

### Setting Up Auto-Assignment

1. Edit a tag
2. Enter an **Auto-assign pattern** (regex)
3. New songs matching the pattern are automatically tagged

### Pattern Examples

| Pattern | Matches |
|---------|---------|
| `(?i)christmas` | Any title containing "christmas" (case-insensitive) |
| `(?i)(workout&#124;exercise&#124;gym)` | Titles with workout, exercise, or gym |
| `^Live` | Titles starting with "Live" |
| `remix$` | Titles ending with "remix" |

## Browsing Tagged Songs

### By Single Tag

1. Click a tag in the sidebar
2. View all songs with that tag

### Include Child Tags

When viewing a parent tag:

1. Toggle **Include children** on
2. See songs from parent and all child tags

### Filter by Tag

In the Songs view:

1. Open the tag filter
2. Select one or more tags
3. View matching songs

## Using Tags in Smart Playlists

Tags are powerful in smart playlists:

### Has Specific Tag

```
Tag: has "Workout"
```

### Has Any of These Tags

```
Tag: has any ["Happy", "Energetic", "Upbeat"]
```

### Combined with Other Rules

```
Tag: has "Favorites"
AND Year: greater than 2010
AND Genre: contains "Pop"
```

## Tag Management

### Editing Tags

1. Right-click a tag
2. Select **Edit**
3. Modify name, color, or parent
4. Click **Save**

### Deleting Tags

1. Right-click a tag
2. Select **Delete**
3. Confirm

**Note**: Deleting a tag removes it from all songs. Songs are not deleted.

### Merging Tags

To combine duplicate tags:

1. View songs with the tag to remove
2. Select all songs
3. Add the target tag
4. Delete the original tag

## Best Practices

### Use Clear Names

- "Workout" not "Gym stuff"
- "Relaxing" not "chill vibes lol"

### Limit Tag Count

Aim for 20-50 well-defined tags rather than hundreds of specific ones.

### Use Hierarchy

Group related tags under parents for easier navigation.

### Combine with Smart Folders

- Smart folders: Physical organization (source, quality)
- Tags: Conceptual organization (mood, activity)

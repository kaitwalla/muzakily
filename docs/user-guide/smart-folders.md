# Smart Folders

Smart folders automatically organize your music based on the file structure in storage. They provide a familiar folder-based view of your library.

## How Smart Folders Work

When music is added to your library, Muzakily reads the file path and creates a matching folder structure:

**Storage Structure:**
```
music/
├── Rock/
│   ├── Classic Rock/
│   │   └── led-zeppelin-iv.flac
│   └── Alternative/
│       └── nevermind.flac
├── Pop/
│   └── thriller.flac
└── Jazz/
    └── kind-of-blue.flac
```

**Resulting Smart Folders:**
- music (4 songs)
  - Rock (2 songs)
    - Classic Rock (1 song)
    - Alternative (1 song)
  - Pop (1 song)
  - Jazz (1 song)

## Browsing Smart Folders

### Folder Tree

1. Click **Smart Folders** in the sidebar
2. Expand folders by clicking the arrow
3. Click a folder to view its contents

### Folder Contents

When you select a folder, you see:

- Songs directly in that folder
- Songs in all subfolders
- Total count for the folder hierarchy

### Navigation

- **Breadcrumbs**: Shows your current location
- **Parent Folder**: Click to go up one level
- **Subfolders**: Displayed at the top of content

## Organization Strategies

### By Genre

```
Music/
├── Rock/
├── Pop/
├── Jazz/
├── Classical/
└── Electronic/
```

### By Decade

```
Music/
├── 1960s/
├── 1970s/
├── 1980s/
├── 1990s/
├── 2000s/
└── 2010s/
```

### By Source

```
Music/
├── CD Rips/
├── Vinyl Rips/
├── Downloads/
└── Streaming Purchases/
```

### By Quality

```
Music/
├── Hi-Res/
│   ├── 24-96/
│   └── 24-192/
├── Lossless/
└── Lossy/
```

### Combined

```
Music/
├── Rock/
│   ├── 1970s/
│   └── 1980s/
├── Jazz/
│   ├── Classic/
│   └── Modern/
└── Classical/
    ├── Baroque/
    └── Romantic/
```

## Using Smart Folders

### Play All Songs

1. Navigate to a folder
2. Click **Play All** or **Shuffle**
3. All songs in the folder (and subfolders) are queued

### Add to Playlist

1. Right-click a folder
2. Select **Add to Playlist**
3. All folder songs are added

### Filter in Song View

Smart folders appear as a filter option in the Songs view:

1. Go to **Songs**
2. Open the **Folder** filter
3. Select a smart folder
4. Only songs in that folder are shown

## Smart Folders in Smart Playlists

Use smart folders as a rule in smart playlists:

```
Smart Folder: equals "Music/Rock"
AND Year: greater than 2000
```

This creates a dynamic playlist of rock songs from the 2000s onward.

## Automatic Updates

Smart folders update automatically:

- **New uploads**: Added to appropriate folder
- **Library scans**: New files create new folders
- **Deletions**: Empty folders are removed

## Differences from Regular Folders

| Feature | Smart Folders | Regular Folders |
|---------|---------------|-----------------|
| Creation | Automatic | Manual |
| Based on | File paths | User choice |
| Updates | Automatic | Manual |
| Editing | Read-only | Editable |

## Tips

### Consistent Naming

Use consistent folder names in your storage for predictable organization.

### Avoid Deep Nesting

Deep folder structures (5+ levels) can be harder to navigate.

### Use with Tags

Combine smart folders with tags for flexible organization:
- Smart folders for broad categories
- Tags for cross-cutting concerns

### Planning Your Structure

Before uploading large libraries, plan your folder structure to match how you want to browse music.

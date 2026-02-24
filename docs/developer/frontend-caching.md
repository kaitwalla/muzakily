# Frontend Caching

The Vue frontend uses IndexedDB for caching playlist and song data to improve performance and enable offline access.

## Architecture

### Cache Utilities

Located in `resources/js/utils/playlistCache.ts`:

```typescript
// Store song in IndexedDB
export async function cacheSong(song: Song): Promise<void>;

// Update an existing cached song
export async function updateCachedSong(song: Song): Promise<void>;

// Retrieve song from cache
export async function getCachedSong(songId: string): Promise<Song | null>;

// Store playlist songs in batch
export async function cachePlaylistSongs(playlistId: number, songs: Song[]): Promise<void>;

// Retrieve cached playlist songs
export async function getCachedPlaylistSongs(playlistId: number): Promise<Song[]>;
```

## Song Metadata Sync

When song metadata is updated (title, artist, tags, etc.), the cache is automatically updated:

```typescript
// In stores/songs.ts
function updateSongInList(updatedSong: Song) {
  // Update in-memory state
  const index = songs.value.findIndex(s => s.id === updatedSong.id);
  if (index !== -1) {
    songs.value[index] = updatedSong;
  }

  // Update IndexedDB cache
  updateCachedSong(updatedSong);
}
```

This ensures:

1. **Persistence across page loads** - Edited song data survives browser refresh
2. **Consistency across views** - Changes appear immediately in all playlists containing the song
3. **Offline availability** - Updated metadata is available when offline

## Cache Invalidation

The cache is invalidated when:

- User logs out
- Explicit cache clear (settings menu)
- Cache version mismatch after app update

## Storage Limits

IndexedDB storage limits vary by browser:

| Browser | Limit |
|---------|-------|
| Chrome | 60% of available disk space |
| Firefox | 50% of available disk space |
| Safari | 1GB (user can grant more) |

The app handles storage quota errors gracefully by falling back to network requests.

## Performance Benefits

- **Instant playlist loading** - Cached playlists display immediately while fetching updates
- **Reduced API calls** - Only fetch changed data using `updated_since` parameter
- **Smoother scrolling** - Large playlists don't require repeated network requests

## Debugging

In browser DevTools:

1. Open **Application** tab
2. Navigate to **IndexedDB**
3. Expand the **muzakily** database
4. View cached songs and playlists

import type { Song } from '@/types/models';

const DB_NAME = 'muzakily';
const DB_VERSION = 2;
const SONGS_STORE = 'songs';
const PLAYLIST_SONGS_STORE = 'playlistSongs';

interface CachedPlaylistPivot {
    playlistId: string;
    updatedAt: string;
    songIds: string[];
}

interface CachedSong {
    id: string;
    data: Song;
    cachedAt: number;
}

let dbPromise: Promise<IDBDatabase> | null = null;

function openDatabase(): Promise<IDBDatabase> {
    if (dbPromise) return dbPromise;

    dbPromise = new Promise((resolve, reject) => {
        const request = indexedDB.open(DB_NAME, DB_VERSION);

        request.onerror = () => {
            reject(request.error);
        };

        request.onsuccess = () => {
            resolve(request.result);
        };

        request.onupgradeneeded = (event) => {
            const db = (event.target as IDBOpenDBRequest).result;

            // Delete old stores if upgrading from v1
            if (event.oldVersion < 2) {
                if (db.objectStoreNames.contains('playlistSongs')) {
                    db.deleteObjectStore('playlistSongs');
                }
            }

            // Songs store - caches individual song metadata
            if (!db.objectStoreNames.contains(SONGS_STORE)) {
                db.createObjectStore(SONGS_STORE, { keyPath: 'id' });
            }

            // Playlist pivot store - maps playlist to song IDs
            if (!db.objectStoreNames.contains(PLAYLIST_SONGS_STORE)) {
                db.createObjectStore(PLAYLIST_SONGS_STORE, { keyPath: 'playlistId' });
            }
        };
    });

    return dbPromise;
}

/**
 * Get cached songs for a playlist if the cache is still valid.
 * Returns null if cache is stale or missing.
 */
export async function getCachedPlaylistSongs(
    playlistId: string,
    playlistUpdatedAt: string
): Promise<Song[] | null> {
    try {
        const db = await openDatabase();

        // First, check if we have a valid pivot for this playlist
        const pivot = await new Promise<CachedPlaylistPivot | undefined>((resolve, reject) => {
            const transaction = db.transaction(PLAYLIST_SONGS_STORE, 'readonly');
            const store = transaction.objectStore(PLAYLIST_SONGS_STORE);
            const request = store.get(playlistId);
            request.onerror = () => reject(request.error);
            request.onsuccess = () => resolve(request.result);
        });

        if (!pivot || pivot.updatedAt !== playlistUpdatedAt) {
            return null;
        }

        // Pivot is valid - fetch all songs from the songs store
        const songs = await getSongsByIds(pivot.songIds);

        // If any songs are missing from cache, invalidate
        if (songs.length !== pivot.songIds.length) {
            return null;
        }

        // Return songs in the correct order (matching pivot order)
        const songMap = new Map(songs.map(s => [s.id, s]));
        const orderedSongs: Song[] = [];
        for (const id of pivot.songIds) {
            const song = songMap.get(id);
            if (song) {
                orderedSongs.push(song);
            }
        }

        return orderedSongs;
    } catch (error) {
        console.warn('Failed to read from playlist cache:', error);
        return null;
    }
}

/**
 * Get multiple songs by their IDs from the cache.
 */
async function getSongsByIds(ids: string[]): Promise<Song[]> {
    if (ids.length === 0) return [];

    const db = await openDatabase();
    const transaction = db.transaction(SONGS_STORE, 'readonly');
    const store = transaction.objectStore(SONGS_STORE);

    const songs: Song[] = [];

    await Promise.all(
        ids.map(id => new Promise<void>((resolve, reject) => {
            const request = store.get(id);
            request.onerror = () => reject(request.error);
            request.onsuccess = () => {
                const cached = request.result as CachedSong | undefined;
                if (cached) {
                    songs.push(cached.data);
                }
                resolve();
            };
        }))
    );

    return songs;
}

/**
 * Cache songs and the playlist pivot.
 */
export async function cachePlaylistSongs(
    playlistId: string,
    playlistUpdatedAt: string,
    songs: Song[]
): Promise<void> {
    try {
        const db = await openDatabase();

        // Cache all songs
        const songsTransaction = db.transaction(SONGS_STORE, 'readwrite');
        const songsStore = songsTransaction.objectStore(SONGS_STORE);
        const now = Date.now();

        for (const song of songs) {
            const cachedSong: CachedSong = {
                id: song.id,
                data: song,
                cachedAt: now,
            };
            songsStore.put(cachedSong);
        }

        await new Promise<void>((resolve, reject) => {
            songsTransaction.oncomplete = () => resolve();
            songsTransaction.onerror = () => reject(songsTransaction.error);
        });

        // Cache the playlist pivot
        const pivotTransaction = db.transaction(PLAYLIST_SONGS_STORE, 'readwrite');
        const pivotStore = pivotTransaction.objectStore(PLAYLIST_SONGS_STORE);

        const pivot: CachedPlaylistPivot = {
            playlistId,
            updatedAt: playlistUpdatedAt,
            songIds: songs.map(s => s.id),
        };

        await new Promise<void>((resolve, reject) => {
            const request = pivotStore.put(pivot);
            request.onerror = () => reject(request.error);
            request.onsuccess = () => resolve();
        });
    } catch (error) {
        console.warn('Failed to write to playlist cache:', error);
    }
}

/**
 * Clear the pivot cache for a specific playlist.
 */
export async function clearPlaylistCache(playlistId: string): Promise<void> {
    try {
        const db = await openDatabase();
        const transaction = db.transaction(PLAYLIST_SONGS_STORE, 'readwrite');
        const store = transaction.objectStore(PLAYLIST_SONGS_STORE);

        await new Promise<void>((resolve, reject) => {
            const request = store.delete(playlistId);
            request.onerror = () => reject(request.error);
            request.onsuccess = () => resolve();
        });
    } catch (error) {
        console.warn('Failed to clear playlist cache:', error);
    }
}

/**
 * Clear all cached data.
 */
export async function clearAllCache(): Promise<void> {
    try {
        const db = await openDatabase();

        const transaction = db.transaction([SONGS_STORE, PLAYLIST_SONGS_STORE], 'readwrite');

        await Promise.all([
            new Promise<void>((resolve, reject) => {
                const request = transaction.objectStore(SONGS_STORE).clear();
                request.onerror = () => reject(request.error);
                request.onsuccess = () => resolve();
            }),
            new Promise<void>((resolve, reject) => {
                const request = transaction.objectStore(PLAYLIST_SONGS_STORE).clear();
                request.onerror = () => reject(request.error);
                request.onsuccess = () => resolve();
            }),
        ]);
    } catch (error) {
        console.warn('Failed to clear all cache:', error);
    }
}

/**
 * Update a single song in the cache.
 * Useful when song metadata is edited.
 */
export async function updateCachedSong(song: Song): Promise<void> {
    try {
        const db = await openDatabase();
        const transaction = db.transaction(SONGS_STORE, 'readwrite');
        const store = transaction.objectStore(SONGS_STORE);

        const cachedSong: CachedSong = {
            id: song.id,
            data: song,
            cachedAt: Date.now(),
        };

        await new Promise<void>((resolve, reject) => {
            const request = store.put(cachedSong);
            request.onerror = () => reject(request.error);
            request.onsuccess = () => resolve();
        });
    } catch (error) {
        console.warn('Failed to update cached song:', error);
    }
}

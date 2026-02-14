import { describe, it, expect, beforeEach, vi } from 'vitest';
import { createPinia, setActivePinia } from 'pinia';
import { usePlaylistsStore } from '@/stores/playlists';
import * as playlistsApi from '@/api/playlists';
import { createMockPlaylist, createMockSong } from '../utils/test-helpers';

vi.mock('@/api/playlists');

describe('usePlaylistsStore', () => {
    beforeEach(() => {
        setActivePinia(createPinia());
        vi.clearAllMocks();
    });

    describe('initial state', () => {
        it('should have empty playlists array', () => {
            const store = usePlaylistsStore();
            expect(store.playlists).toEqual([]);
        });

        it('should have null currentPlaylist', () => {
            const store = usePlaylistsStore();
            expect(store.currentPlaylist).toBeNull();
        });

        it('should have empty currentPlaylistSongs', () => {
            const store = usePlaylistsStore();
            expect(store.currentPlaylistSongs).toEqual([]);
        });

        it('should not be loading', () => {
            const store = usePlaylistsStore();
            expect(store.loading).toBe(false);
        });

        it('should have no error', () => {
            const store = usePlaylistsStore();
            expect(store.error).toBeNull();
        });
    });

    describe('computed properties', () => {
        it('should return hasPlaylists false when empty', () => {
            const store = usePlaylistsStore();
            expect(store.hasPlaylists).toBe(false);
        });

        it('should return hasPlaylists true when playlists exist', () => {
            const store = usePlaylistsStore();
            store.playlists = [createMockPlaylist()];
            expect(store.hasPlaylists).toBe(true);
        });

        it('should return hasMore false when no meta', () => {
            const store = usePlaylistsStore();
            expect(store.hasMore).toBe(false);
        });

        it('should return hasMore true when more pages exist', () => {
            const store = usePlaylistsStore();
            store.meta = { current_page: 1, last_page: 2, per_page: 20, total: 30 };
            expect(store.hasMore).toBe(true);
        });
    });

    describe('fetchPlaylists', () => {
        it('should fetch and set playlists', async () => {
            const mockPlaylists = [createMockPlaylist({ id: 1 }), createMockPlaylist({ id: 2 })];
            vi.mocked(playlistsApi.getPlaylists).mockResolvedValue({
                data: mockPlaylists,
                meta: { current_page: 1, last_page: 1, per_page: 20, total: 2 },
            });

            const store = usePlaylistsStore();
            await store.fetchPlaylists();

            expect(store.playlists).toEqual(mockPlaylists);
        });

        it('should set error on failure', async () => {
            vi.mocked(playlistsApi.getPlaylists).mockRejectedValue(new Error('Network error'));

            const store = usePlaylistsStore();

            await expect(store.fetchPlaylists()).rejects.toThrow();
            expect(store.error).toBe('Failed to load playlists');
        });
    });

    describe('loadMore', () => {
        it('should append more playlists', async () => {
            const initialPlaylists = [createMockPlaylist({ id: 1 })];
            const morePlaylists = [createMockPlaylist({ id: 2 })];

            vi.mocked(playlistsApi.getPlaylists).mockResolvedValue({
                data: morePlaylists,
                meta: { current_page: 2, last_page: 2, per_page: 20, total: 2 },
            });

            const store = usePlaylistsStore();
            store.playlists = initialPlaylists;
            store.meta = { current_page: 1, last_page: 2, per_page: 20, total: 2 };

            await store.loadMore();

            expect(store.playlists).toHaveLength(2);
        });

        it('should not load more when on last page', async () => {
            const store = usePlaylistsStore();
            store.meta = { current_page: 2, last_page: 2, per_page: 20, total: 40 };

            await store.loadMore();

            expect(playlistsApi.getPlaylists).not.toHaveBeenCalled();
        });
    });

    describe('fetchPlaylist', () => {
        it('should fetch playlist by ID', async () => {
            const mockPlaylist = createMockPlaylist({ id: '1' });
            vi.mocked(playlistsApi.getPlaylist).mockResolvedValue(mockPlaylist);

            const store = usePlaylistsStore();
            const result = await store.fetchPlaylist('1');

            expect(result).toEqual(mockPlaylist);
            expect(store.currentPlaylist).toEqual(mockPlaylist);
        });

        it('should fetch playlist by slug (UUID)', async () => {
            const mockPlaylist = createMockPlaylist({ id: 'abc-123', slug: 'abc-123' });
            vi.mocked(playlistsApi.getPlaylist).mockResolvedValue(mockPlaylist);

            const store = usePlaylistsStore();
            const result = await store.fetchPlaylist('abc-123');

            expect(result).toEqual(mockPlaylist);
            expect(playlistsApi.getPlaylist).toHaveBeenCalledWith('abc-123');
        });
    });

    describe('fetchPlaylistSongs', () => {
        it('should fetch and set playlist songs', async () => {
            const mockSongs = [createMockSong({ id: '1' }), createMockSong({ id: '2' })];
            vi.mocked(playlistsApi.getPlaylistSongs).mockResolvedValue(mockSongs);

            const store = usePlaylistsStore();
            const result = await store.fetchPlaylistSongs('1');

            expect(result).toEqual(mockSongs);
            expect(store.currentPlaylistSongs).toEqual(mockSongs);
        });
    });

    describe('createPlaylist', () => {
        it('should create and add playlist to list', async () => {
            const newPlaylist = createMockPlaylist({ id: '3', name: 'New Playlist' });
            vi.mocked(playlistsApi.createPlaylist).mockResolvedValue(newPlaylist);

            const store = usePlaylistsStore();
            store.playlists = [createMockPlaylist({ id: '1' }), createMockPlaylist({ id: '2' })];

            const result = await store.createPlaylist({ name: 'New Playlist' });

            expect(result).toEqual(newPlaylist);
            expect(store.playlists[0]).toEqual(newPlaylist);
            expect(store.playlists).toHaveLength(3);
        });
    });

    describe('updatePlaylist', () => {
        it('should update playlist in list', async () => {
            const updatedPlaylist = createMockPlaylist({ id: '1', name: 'Updated Name' });
            vi.mocked(playlistsApi.updatePlaylist).mockResolvedValue(updatedPlaylist);

            const store = usePlaylistsStore();
            store.playlists = [createMockPlaylist({ id: '1', name: 'Original' })];

            const result = await store.updatePlaylist('1', { name: 'Updated Name' });

            expect(result).toEqual(updatedPlaylist);
            expect(store.playlists[0].name).toBe('Updated Name');
        });

        it('should update currentPlaylist if same ID', async () => {
            const updatedPlaylist = createMockPlaylist({ id: '1', name: 'Updated' });
            vi.mocked(playlistsApi.updatePlaylist).mockResolvedValue(updatedPlaylist);

            const store = usePlaylistsStore();
            store.playlists = [createMockPlaylist({ id: '1' })];
            store.currentPlaylist = createMockPlaylist({ id: '1' });

            await store.updatePlaylist('1', { name: 'Updated' });

            expect(store.currentPlaylist?.name).toBe('Updated');
        });
    });

    describe('deletePlaylist', () => {
        it('should remove playlist from list', async () => {
            vi.mocked(playlistsApi.deletePlaylist).mockResolvedValue();

            const store = usePlaylistsStore();
            store.playlists = [
                createMockPlaylist({ id: '1' }),
                createMockPlaylist({ id: '2' }),
            ];

            await store.deletePlaylist('1');

            expect(store.playlists).toHaveLength(1);
            expect(store.playlists[0].id).toBe('2');
        });

        it('should clear currentPlaylist if deleted', async () => {
            vi.mocked(playlistsApi.deletePlaylist).mockResolvedValue();

            const store = usePlaylistsStore();
            store.playlists = [createMockPlaylist({ id: '1' })];
            store.currentPlaylist = createMockPlaylist({ id: '1' });
            store.currentPlaylistSongs = [createMockSong()];

            await store.deletePlaylist('1');

            expect(store.currentPlaylist).toBeNull();
            expect(store.currentPlaylistSongs).toEqual([]);
        });
    });

    describe('addSongsToPlaylist', () => {
        it('should call API and refresh songs', async () => {
            vi.mocked(playlistsApi.addSongsToPlaylist).mockResolvedValue();
            vi.mocked(playlistsApi.getPlaylistSongs).mockResolvedValue([createMockSong()]);

            const store = usePlaylistsStore();
            store.currentPlaylist = createMockPlaylist({ id: '1' });

            await store.addSongsToPlaylist('1', ['song-1', 'song-2']);

            expect(playlistsApi.addSongsToPlaylist).toHaveBeenCalledWith('1', ['song-1', 'song-2']);
            expect(playlistsApi.getPlaylistSongs).toHaveBeenCalledWith('1');
            expect(store.currentPlaylistSongs).toHaveLength(1);
        });
    });

    describe('removeSongsFromPlaylist', () => {
        it('should remove songs from currentPlaylistSongs', async () => {
            vi.mocked(playlistsApi.removeSongsFromPlaylist).mockResolvedValue();

            const store = usePlaylistsStore();
            store.currentPlaylist = createMockPlaylist({ id: '1' });
            store.currentPlaylistSongs = [
                createMockSong({ id: '1' }),
                createMockSong({ id: '2' }),
                createMockSong({ id: '3' }),
            ];

            await store.removeSongsFromPlaylist('1', ['1', '3']);

            expect(store.currentPlaylistSongs).toHaveLength(1);
            expect(store.currentPlaylistSongs[0].id).toBe('2');
        });
    });

    describe('clearPlaylists', () => {
        it('should clear playlists and meta', () => {
            const store = usePlaylistsStore();
            store.playlists = [createMockPlaylist()];
            store.meta = { current_page: 1, last_page: 1, per_page: 20, total: 1 };

            store.clearPlaylists();

            expect(store.playlists).toEqual([]);
            expect(store.meta).toBeNull();
        });
    });

    describe('clearCurrentPlaylist', () => {
        it('should clear currentPlaylist and songs', () => {
            const store = usePlaylistsStore();
            store.currentPlaylist = createMockPlaylist();
            store.currentPlaylistSongs = [createMockSong()];

            store.clearCurrentPlaylist();

            expect(store.currentPlaylist).toBeNull();
            expect(store.currentPlaylistSongs).toEqual([]);
        });
    });
});

import { describe, it, expect, beforeEach, vi } from 'vitest';
import { createPinia, setActivePinia } from 'pinia';
import { useSongsStore } from '@/stores/songs';
import * as songsApi from '@/api/songs';
import { createMockSong } from '../utils/test-helpers';

vi.mock('@/api/songs');

describe('useSongsStore', () => {
    beforeEach(() => {
        setActivePinia(createPinia());
        vi.clearAllMocks();
    });

    describe('initial state', () => {
        it('should have empty songs array', () => {
            const store = useSongsStore();
            expect(store.songs).toEqual([]);
        });

        it('should have null currentSong', () => {
            const store = useSongsStore();
            expect(store.currentSong).toBeNull();
        });

        it('should not be loading', () => {
            const store = useSongsStore();
            expect(store.loading).toBe(false);
        });

        it('should have no error', () => {
            const store = useSongsStore();
            expect(store.error).toBeNull();
        });
    });

    describe('computed properties', () => {
        it('should return hasSongs false when empty', () => {
            const store = useSongsStore();
            expect(store.hasSongs).toBe(false);
        });

        it('should return hasSongs true when songs exist', () => {
            const store = useSongsStore();
            store.songs = [createMockSong()];
            expect(store.hasSongs).toBe(true);
        });

        it('should return hasMore false when no meta', () => {
            const store = useSongsStore();
            expect(store.hasMore).toBe(false);
        });

        it('should return hasMore true when more pages exist', () => {
            const store = useSongsStore();
            store.meta = {
                current_page: 1,
                last_page: 3,
                per_page: 20,
                total: 60,
            };
            expect(store.hasMore).toBe(true);
        });

        it('should return hasMore false on last page', () => {
            const store = useSongsStore();
            store.meta = {
                current_page: 3,
                last_page: 3,
                per_page: 20,
                total: 60,
            };
            expect(store.hasMore).toBe(false);
        });
    });

    describe('fetchSongs', () => {
        it('should fetch and set songs', async () => {
            const mockSongs = [createMockSong({ id: '1' }), createMockSong({ id: '2' })];
            vi.mocked(songsApi.getSongs).mockResolvedValue({
                data: mockSongs,
                meta: {
                    current_page: 1,
                    last_page: 1,
                    per_page: 20,
                    total: 2,
                },
            });

            const store = useSongsStore();
            await store.fetchSongs();

            expect(store.songs).toEqual(mockSongs);
            expect(store.meta).toBeDefined();
        });

        it('should set loading during fetch', async () => {
            vi.mocked(songsApi.getSongs).mockImplementation(async () => {
                return new Promise((resolve) => {
                    setTimeout(() => resolve({
                        data: [],
                        meta: { current_page: 1, last_page: 1, per_page: 20, total: 0 },
                    }), 10);
                });
            });

            const store = useSongsStore();
            const fetchPromise = store.fetchSongs();

            expect(store.loading).toBe(true);
            await fetchPromise;
            expect(store.loading).toBe(false);
        });

        it('should set error on fetch failure', async () => {
            vi.mocked(songsApi.getSongs).mockRejectedValue(new Error('Network error'));

            const store = useSongsStore();

            await expect(store.fetchSongs()).rejects.toThrow();
            expect(store.error).toBe('Failed to load songs');
        });

        it('should pass filters to API', async () => {
            vi.mocked(songsApi.getSongs).mockResolvedValue({
                data: [],
                meta: { current_page: 1, last_page: 1, per_page: 20, total: 0 },
            });

            const store = useSongsStore();
            await store.fetchSongs({ artist_id: '1', search: 'rock' });

            expect(songsApi.getSongs).toHaveBeenCalledWith({ artist_id: '1', search: 'rock' });
        });
    });

    describe('loadMore', () => {
        it('should append more songs', async () => {
            const initialSongs = [createMockSong({ id: '1' })];
            const moreSongs = [createMockSong({ id: '2' })];

            vi.mocked(songsApi.getSongs).mockResolvedValue({
                data: moreSongs,
                meta: { current_page: 2, last_page: 2, per_page: 20, total: 2 },
            });

            const store = useSongsStore();
            store.songs = initialSongs;
            store.meta = { current_page: 1, last_page: 2, per_page: 20, total: 2 };

            await store.loadMore();

            expect(store.songs).toHaveLength(2);
            expect(store.songs[1]).toEqual(moreSongs[0]);
        });

        it('should not load more when no more pages', async () => {
            const store = useSongsStore();
            store.meta = { current_page: 2, last_page: 2, per_page: 20, total: 40 };

            await store.loadMore();

            expect(songsApi.getSongs).not.toHaveBeenCalled();
        });

        it('should not load more while loading', async () => {
            const store = useSongsStore();
            store.loading = true;
            store.meta = { current_page: 1, last_page: 2, per_page: 20, total: 40 };

            await store.loadMore();

            expect(songsApi.getSongs).not.toHaveBeenCalled();
        });
    });

    describe('fetchSong', () => {
        it('should fetch and set current song', async () => {
            const mockSong = createMockSong({ id: '1' });
            vi.mocked(songsApi.getSong).mockResolvedValue(mockSong);

            const store = useSongsStore();
            const result = await store.fetchSong('1');

            expect(result).toEqual(mockSong);
            expect(store.currentSong).toEqual(mockSong);
        });

        it('should set error on fetch failure', async () => {
            vi.mocked(songsApi.getSong).mockRejectedValue(new Error('Not found'));

            const store = useSongsStore();

            await expect(store.fetchSong('invalid')).rejects.toThrow();
            expect(store.error).toBe('Failed to load song');
        });
    });

    describe('getSongStreamUrl', () => {
        it('should return stream URL from API', () => {
            vi.mocked(songsApi.getStreamUrl).mockReturnValue('/api/v1/songs/1/stream');

            const store = useSongsStore();
            const url = store.getSongStreamUrl('1');

            expect(url).toBe('/api/v1/songs/1/stream');
        });
    });

    describe('clearSongs', () => {
        it('should clear songs and meta', () => {
            const store = useSongsStore();
            store.songs = [createMockSong()];
            store.meta = { current_page: 1, last_page: 1, per_page: 20, total: 1 };

            store.clearSongs();

            expect(store.songs).toEqual([]);
            expect(store.meta).toBeNull();
        });
    });
});

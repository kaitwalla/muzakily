import { describe, it, expect, beforeEach, vi } from 'vitest';
import { createPinia, setActivePinia } from 'pinia';
import { useSearchStore } from '@/stores/search';
import * as searchApi from '@/api/search';
import { createMockSong, createMockAlbum, createMockArtist, createMockPlaylist } from '../utils/test-helpers';

vi.mock('@/api/search');

describe('useSearchStore', () => {
    beforeEach(() => {
        setActivePinia(createPinia());
        vi.clearAllMocks();
    });

    describe('initial state', () => {
        it('should have empty query', () => {
            const store = useSearchStore();
            expect(store.query).toBe('');
        });

        it('should have empty results arrays', () => {
            const store = useSearchStore();
            expect(store.songs).toEqual([]);
            expect(store.albums).toEqual([]);
            expect(store.artists).toEqual([]);
            expect(store.playlists).toEqual([]);
        });

        it('should not be loading', () => {
            const store = useSearchStore();
            expect(store.loading).toBe(false);
        });

        it('should not have searched', () => {
            const store = useSearchStore();
            expect(store.hasSearched).toBe(false);
        });
    });

    describe('computed properties', () => {
        it('should return hasResults false when empty', () => {
            const store = useSearchStore();
            expect(store.hasResults).toBe(false);
        });

        it('should return hasResults true when songs exist', () => {
            const store = useSearchStore();
            store.songs = [createMockSong()];
            expect(store.hasResults).toBe(true);
        });

        it('should return hasResults true when albums exist', () => {
            const store = useSearchStore();
            store.albums = [createMockAlbum()];
            expect(store.hasResults).toBe(true);
        });

        it('should return hasResults true when artists exist', () => {
            const store = useSearchStore();
            store.artists = [createMockArtist()];
            expect(store.hasResults).toBe(true);
        });

        it('should return hasResults true when playlists exist', () => {
            const store = useSearchStore();
            store.playlists = [createMockPlaylist()];
            expect(store.hasResults).toBe(true);
        });

        it('should calculate totalResults correctly', () => {
            const store = useSearchStore();
            store.songs = [createMockSong(), createMockSong({ id: '2' })];
            store.albums = [createMockAlbum()];
            store.artists = [createMockArtist()];
            store.playlists = [];
            expect(store.totalResults).toBe(4);
        });
    });

    describe('search', () => {
        it('should perform search and set results', async () => {
            const mockResults = {
                songs: [createMockSong()],
                albums: [createMockAlbum()],
                artists: [createMockArtist()],
                playlists: [createMockPlaylist()],
            };
            vi.mocked(searchApi.search).mockResolvedValue(mockResults);

            const store = useSearchStore();
            await store.search('test query');

            expect(store.query).toBe('test query');
            expect(store.songs).toEqual(mockResults.songs);
            expect(store.albums).toEqual(mockResults.albums);
            expect(store.artists).toEqual(mockResults.artists);
            expect(store.playlists).toEqual(mockResults.playlists);
            expect(store.hasSearched).toBe(true);
        });

        it('should clear results for empty query', async () => {
            const store = useSearchStore();
            store.songs = [createMockSong()];
            store.hasSearched = true;

            await store.search('   ');

            expect(store.songs).toEqual([]);
            expect(store.hasSearched).toBe(false);
        });

        it('should set loading during search', async () => {
            vi.mocked(searchApi.search).mockImplementation(async () => {
                return new Promise((resolve) => {
                    setTimeout(() => resolve({
                        songs: [],
                        albums: [],
                        artists: [],
                        playlists: [],
                    }), 10);
                });
            });

            const store = useSearchStore();
            const searchPromise = store.search('test');

            expect(store.loading).toBe(true);
            await searchPromise;
            expect(store.loading).toBe(false);
        });

        it('should set error on search failure', async () => {
            vi.mocked(searchApi.search).mockRejectedValue(new Error('Search error'));

            const store = useSearchStore();

            await expect(store.search('test')).rejects.toThrow();
            expect(store.error).toBe('Search failed');
        });

        it('should pass type filter to API', async () => {
            vi.mocked(searchApi.search).mockResolvedValue({
                songs: [],
                albums: [],
                artists: [],
                playlists: [],
            });

            const store = useSearchStore();
            await store.search('test', 'songs');

            expect(searchApi.search).toHaveBeenCalledWith({ q: 'test', type: 'songs' });
        });
    });

    describe('clearResults', () => {
        it('should clear all search state', () => {
            const store = useSearchStore();
            store.query = 'test';
            store.songs = [createMockSong()];
            store.albums = [createMockAlbum()];
            store.artists = [createMockArtist()];
            store.playlists = [createMockPlaylist()];
            store.hasSearched = true;
            store.error = 'Some error';

            store.clearResults();

            expect(store.query).toBe('');
            expect(store.songs).toEqual([]);
            expect(store.albums).toEqual([]);
            expect(store.artists).toEqual([]);
            expect(store.playlists).toEqual([]);
            expect(store.hasSearched).toBe(false);
            expect(store.error).toBeNull();
        });
    });
});

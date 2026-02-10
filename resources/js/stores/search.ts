import { defineStore } from 'pinia';
import { ref, computed } from 'vue';
import type { Song, Album, Artist, Playlist } from '@/types/models';
import * as searchApi from '@/api/search';

export const useSearchStore = defineStore('search', () => {
    const query = ref('');
    const songs = ref<Song[]>([]);
    const albums = ref<Album[]>([]);
    const artists = ref<Artist[]>([]);
    const playlists = ref<Playlist[]>([]);
    const loading = ref(false);
    const error = ref<string | null>(null);
    const hasSearched = ref(false);

    const hasResults = computed(() => {
        return songs.value.length > 0 ||
            albums.value.length > 0 ||
            artists.value.length > 0 ||
            playlists.value.length > 0;
    });

    const totalResults = computed(() => {
        return songs.value.length +
            albums.value.length +
            artists.value.length +
            playlists.value.length;
    });

    async function search(searchQuery: string, type?: searchApi.SearchFilters['type']): Promise<void> {
        if (!searchQuery.trim()) {
            clearResults();
            return;
        }

        query.value = searchQuery;
        loading.value = true;
        error.value = null;
        hasSearched.value = true;

        try {
            const results = await searchApi.search({ q: searchQuery, type });
            songs.value = results.songs;
            albums.value = results.albums;
            artists.value = results.artists;
            playlists.value = results.playlists;
        } catch (e) {
            error.value = 'Search failed';
            throw e;
        } finally {
            loading.value = false;
        }
    }

    function clearResults(): void {
        query.value = '';
        songs.value = [];
        albums.value = [];
        artists.value = [];
        playlists.value = [];
        hasSearched.value = false;
        error.value = null;
    }

    return {
        query,
        songs,
        albums,
        artists,
        playlists,
        loading,
        error,
        hasSearched,
        hasResults,
        totalResults,
        search,
        clearResults,
    };
});

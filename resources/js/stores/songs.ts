import { defineStore } from 'pinia';
import { ref, computed } from 'vue';
import type { Song } from '@/types/models';
import type { PaginationMeta } from '@/types/api';
import * as songsApi from '@/api/songs';
import { getStreamUrl } from '@/api/songs';

export const useSongsStore = defineStore('songs', () => {
    const songs = ref<Song[]>([]);
    const currentSong = ref<Song | null>(null);
    const loading = ref(false);
    const error = ref<string | null>(null);
    const meta = ref<PaginationMeta | null>(null);
    const currentFilters = ref<songsApi.SongFilters>({});

    const hasSongs = computed(() => songs.value.length > 0);
    const hasMore = computed(() => {
        if (!meta.value) return false;
        return meta.value.current_page < meta.value.last_page;
    });

    async function fetchSongs(filters: songsApi.SongFilters = {}): Promise<void> {
        loading.value = true;
        error.value = null;
        currentFilters.value = filters;
        try {
            const response = await songsApi.getSongs(filters);
            songs.value = response.data;
            meta.value = response.meta;
        } catch (e) {
            error.value = 'Failed to load songs';
            throw e;
        } finally {
            loading.value = false;
        }
    }

    async function loadMore(): Promise<void> {
        if (!hasMore.value || loading.value || !meta.value) return;

        loading.value = true;
        error.value = null;
        try {
            const response = await songsApi.getSongs({
                ...currentFilters.value,
                page: meta.value.current_page + 1,
            });
            songs.value = [...songs.value, ...response.data];
            meta.value = response.meta;
        } catch (e) {
            error.value = 'Failed to load more songs';
            throw e;
        } finally {
            loading.value = false;
        }
    }

    async function fetchSong(idOrSlug: string): Promise<Song> {
        loading.value = true;
        error.value = null;
        try {
            const song = await songsApi.getSong(idOrSlug);
            currentSong.value = song;
            return song;
        } catch (e) {
            error.value = 'Failed to load song';
            throw e;
        } finally {
            loading.value = false;
        }
    }

    function getSongStreamUrl(songId: string): string {
        return getStreamUrl(songId);
    }

    function clearSongs(): void {
        songs.value = [];
        meta.value = null;
    }

    return {
        songs,
        currentSong,
        loading,
        error,
        meta,
        hasSongs,
        hasMore,
        fetchSongs,
        loadMore,
        fetchSong,
        getSongStreamUrl,
        clearSongs,
    };
});

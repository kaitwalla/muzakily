import { defineStore } from 'pinia';
import { ref, computed } from 'vue';
import type { Artist, Album, Song } from '@/types/models';
import type { PaginationMeta } from '@/types/api';
import * as artistsApi from '@/api/artists';

export const useArtistsStore = defineStore('artists', () => {
    const artists = ref<Artist[]>([]);
    const currentArtist = ref<Artist | null>(null);
    const currentArtistAlbums = ref<Album[]>([]);
    const currentArtistSongs = ref<Song[]>([]);
    const loading = ref(false);
    const error = ref<string | null>(null);
    const meta = ref<PaginationMeta | null>(null);
    const currentFilters = ref<artistsApi.ArtistFilters>({});

    const hasArtists = computed(() => artists.value.length > 0);
    const hasMore = computed(() => {
        if (!meta.value) return false;
        return meta.value.current_page < meta.value.last_page;
    });

    async function fetchArtists(filters: artistsApi.ArtistFilters = {}): Promise<void> {
        loading.value = true;
        error.value = null;
        currentFilters.value = filters;
        try {
            const response = await artistsApi.getArtists(filters);
            artists.value = response.data;
            meta.value = response.meta;
        } catch (e) {
            error.value = 'Failed to load artists';
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
            const response = await artistsApi.getArtists({
                ...currentFilters.value,
                page: meta.value.current_page + 1,
            });
            artists.value = [...artists.value, ...response.data];
            meta.value = response.meta;
        } catch (e) {
            error.value = 'Failed to load more artists';
            throw e;
        } finally {
            loading.value = false;
        }
    }

    async function fetchArtist(id: string): Promise<Artist> {
        loading.value = true;
        error.value = null;
        try {
            const artist = await artistsApi.getArtist(id);
            currentArtist.value = artist;
            return artist;
        } catch (e) {
            error.value = 'Failed to load artist';
            throw e;
        } finally {
            loading.value = false;
        }
    }

    async function fetchArtistAlbums(artistId: string): Promise<Album[]> {
        loading.value = true;
        error.value = null;
        try {
            const albums = await artistsApi.getArtistAlbums(artistId);
            currentArtistAlbums.value = albums;
            return albums;
        } catch (e) {
            error.value = 'Failed to load artist albums';
            throw e;
        } finally {
            loading.value = false;
        }
    }

    async function fetchArtistSongs(artistId: string): Promise<Song[]> {
        loading.value = true;
        error.value = null;
        try {
            const songs = await artistsApi.getArtistSongs(artistId);
            currentArtistSongs.value = songs;
            return songs;
        } catch (e) {
            error.value = 'Failed to load artist songs';
            throw e;
        } finally {
            loading.value = false;
        }
    }

    function clearArtists(): void {
        artists.value = [];
        meta.value = null;
    }

    function clearCurrentArtist(): void {
        currentArtist.value = null;
        currentArtistAlbums.value = [];
        currentArtistSongs.value = [];
    }

    return {
        artists,
        currentArtist,
        currentArtistAlbums,
        currentArtistSongs,
        loading,
        error,
        meta,
        hasArtists,
        hasMore,
        fetchArtists,
        loadMore,
        fetchArtist,
        fetchArtistAlbums,
        fetchArtistSongs,
        clearArtists,
        clearCurrentArtist,
    };
});

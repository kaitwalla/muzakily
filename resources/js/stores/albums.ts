import { defineStore } from 'pinia';
import { ref, computed } from 'vue';
import type { Album, Song } from '@/types/models';
import type { PaginationMeta } from '@/types/api';
import * as albumsApi from '@/api/albums';

export const useAlbumsStore = defineStore('albums', () => {
    const albums = ref<Album[]>([]);
    const currentAlbum = ref<Album | null>(null);
    const currentAlbumSongs = ref<Song[]>([]);
    const loading = ref(false);
    const error = ref<string | null>(null);
    const meta = ref<PaginationMeta | null>(null);
    const currentFilters = ref<albumsApi.AlbumFilters>({});

    const hasAlbums = computed(() => albums.value.length > 0);
    const hasMore = computed(() => {
        if (!meta.value) return false;
        return meta.value.current_page < meta.value.last_page;
    });

    async function fetchAlbums(filters: albumsApi.AlbumFilters = {}): Promise<void> {
        loading.value = true;
        error.value = null;
        currentFilters.value = filters;
        try {
            const response = await albumsApi.getAlbums(filters);
            albums.value = response.data;
            meta.value = response.meta;
        } catch (e) {
            error.value = 'Failed to load albums';
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
            const response = await albumsApi.getAlbums({
                ...currentFilters.value,
                page: meta.value.current_page + 1,
            });
            albums.value = [...albums.value, ...response.data];
            meta.value = response.meta;
        } catch (e) {
            error.value = 'Failed to load more albums';
            throw e;
        } finally {
            loading.value = false;
        }
    }

    async function fetchAlbum(id: string): Promise<Album> {
        loading.value = true;
        error.value = null;
        try {
            const album = await albumsApi.getAlbum(id);
            currentAlbum.value = album;
            return album;
        } catch (e) {
            error.value = 'Failed to load album';
            throw e;
        } finally {
            loading.value = false;
        }
    }

    async function fetchAlbumSongs(albumId: string): Promise<Song[]> {
        loading.value = true;
        error.value = null;
        try {
            const songs = await albumsApi.getAlbumSongs(albumId);
            currentAlbumSongs.value = songs;
            return songs;
        } catch (e) {
            error.value = 'Failed to load album songs';
            throw e;
        } finally {
            loading.value = false;
        }
    }

    function clearAlbums(): void {
        albums.value = [];
        meta.value = null;
    }

    function clearCurrentAlbum(): void {
        currentAlbum.value = null;
        currentAlbumSongs.value = [];
    }

    function updateSongInAlbum(updatedSong: Song, index: number): void {
        if (index >= 0 && index < currentAlbumSongs.value.length) {
            currentAlbumSongs.value[index] = updatedSong;
        }
    }

    async function uploadCover(albumId: string, file: File): Promise<Album> {
        loading.value = true;
        error.value = null;
        try {
            const album = await albumsApi.uploadAlbumCover(albumId, file);
            if (currentAlbum.value?.id === albumId) {
                currentAlbum.value = album;
            }
            return album;
        } catch (e) {
            error.value = 'Failed to upload cover';
            throw e;
        } finally {
            loading.value = false;
        }
    }

    async function refreshCover(albumId: string): Promise<Album> {
        loading.value = true;
        error.value = null;
        try {
            const album = await albumsApi.refreshAlbumCover(albumId);
            if (currentAlbum.value?.id === albumId) {
                currentAlbum.value = album;
            }
            return album;
        } catch (e) {
            error.value = 'Failed to refresh cover';
            throw e;
        } finally {
            loading.value = false;
        }
    }

    return {
        albums,
        currentAlbum,
        currentAlbumSongs,
        loading,
        error,
        meta,
        hasAlbums,
        hasMore,
        fetchAlbums,
        loadMore,
        fetchAlbum,
        fetchAlbumSongs,
        clearAlbums,
        clearCurrentAlbum,
        updateSongInAlbum,
        uploadCover,
        refreshCover,
    };
});

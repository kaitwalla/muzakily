import { defineStore } from 'pinia';
import { ref, computed } from 'vue';
import type { Playlist, Song } from '@/types/models';
import type { PaginationMeta } from '@/types/api';
import * as playlistsApi from '@/api/playlists';

export const usePlaylistsStore = defineStore('playlists', () => {
    const playlists = ref<Playlist[]>([]);
    const currentPlaylist = ref<Playlist | null>(null);
    const currentPlaylistSongs = ref<Song[]>([]);
    const loading = ref(false);
    const error = ref<string | null>(null);
    const meta = ref<PaginationMeta | null>(null);
    const currentFilters = ref<playlistsApi.PlaylistFilters>({});

    const hasPlaylists = computed(() => playlists.value.length > 0);
    const hasMore = computed(() => {
        if (!meta.value) return false;
        return meta.value.current_page < meta.value.last_page;
    });

    async function fetchPlaylists(filters: playlistsApi.PlaylistFilters = {}): Promise<void> {
        loading.value = true;
        error.value = null;
        currentFilters.value = filters;
        try {
            const response = await playlistsApi.getPlaylists(filters);
            playlists.value = response.data;
            meta.value = response.meta;
        } catch (e) {
            error.value = 'Failed to load playlists';
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
            const response = await playlistsApi.getPlaylists({
                ...currentFilters.value,
                page: meta.value.current_page + 1,
            });
            playlists.value = [...playlists.value, ...response.data];
            meta.value = response.meta;
        } catch (e) {
            error.value = 'Failed to load more playlists';
            throw e;
        } finally {
            loading.value = false;
        }
    }

    async function fetchPlaylist(idOrSlug: number | string): Promise<Playlist> {
        loading.value = true;
        error.value = null;
        try {
            const playlist = typeof idOrSlug === 'number'
                ? await playlistsApi.getPlaylist(idOrSlug)
                : await playlistsApi.getPlaylistBySlug(idOrSlug);
            currentPlaylist.value = playlist;
            return playlist;
        } catch (e) {
            error.value = 'Failed to load playlist';
            throw e;
        } finally {
            loading.value = false;
        }
    }

    async function fetchPlaylistSongs(playlistId: number): Promise<Song[]> {
        loading.value = true;
        error.value = null;
        try {
            const songs = await playlistsApi.getPlaylistSongs(playlistId);
            currentPlaylistSongs.value = songs;
            return songs;
        } catch (e) {
            error.value = 'Failed to load playlist songs';
            throw e;
        } finally {
            loading.value = false;
        }
    }

    async function createPlaylist(data: playlistsApi.CreatePlaylistData): Promise<Playlist> {
        loading.value = true;
        error.value = null;
        try {
            const playlist = await playlistsApi.createPlaylist(data);
            playlists.value = [playlist, ...playlists.value];
            return playlist;
        } catch (e) {
            error.value = 'Failed to create playlist';
            throw e;
        } finally {
            loading.value = false;
        }
    }

    async function updatePlaylist(id: number, data: playlistsApi.UpdatePlaylistData): Promise<Playlist> {
        loading.value = true;
        error.value = null;
        try {
            const playlist = await playlistsApi.updatePlaylist(id, data);
            const index = playlists.value.findIndex(p => p.id === id);
            if (index !== -1) {
                playlists.value[index] = playlist;
            }
            if (currentPlaylist.value?.id === id) {
                currentPlaylist.value = playlist;
            }
            return playlist;
        } catch (e) {
            error.value = 'Failed to update playlist';
            throw e;
        } finally {
            loading.value = false;
        }
    }

    async function deletePlaylist(id: number): Promise<void> {
        loading.value = true;
        error.value = null;
        try {
            await playlistsApi.deletePlaylist(id);
            playlists.value = playlists.value.filter(p => p.id !== id);
            if (currentPlaylist.value?.id === id) {
                currentPlaylist.value = null;
                currentPlaylistSongs.value = [];
            }
        } catch (e) {
            error.value = 'Failed to delete playlist';
            throw e;
        } finally {
            loading.value = false;
        }
    }

    async function addSongsToPlaylist(playlistId: number, songIds: string[]): Promise<void> {
        loading.value = true;
        error.value = null;
        try {
            await playlistsApi.addSongsToPlaylist(playlistId, songIds);
            // Refresh playlist songs if viewing the current playlist
            if (currentPlaylist.value?.id === playlistId) {
                await fetchPlaylistSongs(playlistId);
            }
        } catch (e) {
            error.value = 'Failed to add songs to playlist';
            throw e;
        } finally {
            loading.value = false;
        }
    }

    async function removeSongsFromPlaylist(playlistId: number, songIds: string[]): Promise<void> {
        loading.value = true;
        error.value = null;
        try {
            await playlistsApi.removeSongsFromPlaylist(playlistId, songIds);
            if (currentPlaylist.value?.id === playlistId) {
                currentPlaylistSongs.value = currentPlaylistSongs.value.filter(
                    s => !songIds.includes(s.id)
                );
            }
        } catch (e) {
            error.value = 'Failed to remove songs from playlist';
            throw e;
        } finally {
            loading.value = false;
        }
    }

    async function reorderPlaylistSongs(playlistId: number, songIds: string[]): Promise<void> {
        loading.value = true;
        error.value = null;
        try {
            await playlistsApi.reorderPlaylistSongs(playlistId, songIds);
        } catch (e) {
            error.value = 'Failed to reorder playlist songs';
            throw e;
        } finally {
            loading.value = false;
        }
    }

    function clearPlaylists(): void {
        playlists.value = [];
        meta.value = null;
    }

    function clearCurrentPlaylist(): void {
        currentPlaylist.value = null;
        currentPlaylistSongs.value = [];
    }

    function updateSongInPlaylist(updatedSong: Song, index: number): void {
        if (index >= 0 && index < currentPlaylistSongs.value.length) {
            currentPlaylistSongs.value[index] = updatedSong;
        }
    }

    return {
        playlists,
        currentPlaylist,
        currentPlaylistSongs,
        loading,
        error,
        meta,
        hasPlaylists,
        hasMore,
        fetchPlaylists,
        loadMore,
        fetchPlaylist,
        fetchPlaylistSongs,
        createPlaylist,
        updatePlaylist,
        deletePlaylist,
        addSongsToPlaylist,
        removeSongsFromPlaylist,
        reorderPlaylistSongs,
        clearPlaylists,
        clearCurrentPlaylist,
        updateSongInPlaylist,
    };
});

import { apiClient } from './client';
import type { Playlist, Song } from '@/types/models';
import type { SmartPlaylistRuleGroup } from '@/config/smartPlaylist';
import type { ApiResponse, PaginatedResponse } from '@/types/api';

export interface PlaylistFilters {
    page?: number;
    per_page?: number;
}

export interface CreatePlaylistData {
    name: string;
    description?: string;
    is_public?: boolean;
    is_smart?: boolean;
    rules?: SmartPlaylistRuleGroup[];
}

export interface UpdatePlaylistData {
    name?: string;
    description?: string;
    is_public?: boolean;
    rules?: SmartPlaylistRuleGroup[];
}

export async function getPlaylists(filters: PlaylistFilters = {}): Promise<PaginatedResponse<Playlist>> {
    const response = await apiClient.get<PaginatedResponse<Playlist>>('/playlists', { params: filters });
    return response.data;
}

export async function getPlaylist(id: string): Promise<Playlist> {
    const response = await apiClient.get<ApiResponse<Playlist>>(`/playlists/${id}`);
    return response.data.data;
}

export async function getPlaylistBySlug(slug: string): Promise<Playlist> {
    const response = await apiClient.get<ApiResponse<Playlist>>(`/playlists/${slug}`);
    return response.data.data;
}

export async function createPlaylist(data: CreatePlaylistData): Promise<Playlist> {
    const response = await apiClient.post<ApiResponse<Playlist>>('/playlists', data);
    return response.data.data;
}

export async function updatePlaylist(id: string, data: UpdatePlaylistData): Promise<Playlist> {
    const response = await apiClient.put<ApiResponse<Playlist>>(`/playlists/${id}`, data);
    return response.data.data;
}

export async function deletePlaylist(id: string): Promise<void> {
    await apiClient.delete(`/playlists/${id}`);
}

interface PlaylistSongsResponse {
    data: Song[];
    meta: {
        total: number;
        limit: number;
        offset: number;
        has_more: boolean;
    };
}

export interface PlaylistSongsResult {
    songs: Song[];
    total: number;
}

export async function getPlaylistSongs(playlistId: string): Promise<PlaylistSongsResult> {
    const allSongs: Song[] = [];
    let offset = 0;
    const limit = 500; // Max allowed by API
    let hasMore = true;
    let total = 0;

    while (hasMore) {
        const response = await apiClient.get<PlaylistSongsResponse>(`/playlists/${playlistId}/songs`, {
            params: { offset, limit }
        });

        const newSongs = response.data.data;
        allSongs.push(...newSongs);
        total = response.data.meta.total;
        hasMore = response.data.meta.has_more;
        offset += limit;

        // Safeguard against infinite loops
        if (newSongs.length === 0 || offset >= total) {
            break;
        }
    }

    return { songs: allSongs, total };
}

export async function addSongsToPlaylist(playlistId: string, songIds: string[]): Promise<void> {
    await apiClient.post(`/playlists/${playlistId}/songs`, { song_ids: songIds });
}

export async function removeSongsFromPlaylist(playlistId: string, songIds: string[]): Promise<void> {
    await apiClient.delete(`/playlists/${playlistId}/songs`, { data: { song_ids: songIds } });
}

export async function reorderPlaylistSongs(playlistId: string, songIds: string[]): Promise<void> {
    await apiClient.put(`/playlists/${playlistId}/songs/reorder`, { song_ids: songIds });
}

export async function refreshPlaylistCover(playlistId: string): Promise<Playlist> {
    const response = await apiClient.post<ApiResponse<Playlist>>(`/playlists/${playlistId}/refresh-cover`);
    return response.data.data;
}

export async function uploadPlaylistCover(playlistId: string, file: File): Promise<Playlist> {
    const formData = new FormData();
    formData.append('cover', file);
    const response = await apiClient.post<ApiResponse<Playlist>>(`/playlists/${playlistId}/cover`, formData);
    return response.data.data;
}

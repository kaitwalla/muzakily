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

export async function getPlaylist(id: number): Promise<Playlist> {
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

export async function updatePlaylist(id: number, data: UpdatePlaylistData): Promise<Playlist> {
    const response = await apiClient.put<ApiResponse<Playlist>>(`/playlists/${id}`, data);
    return response.data.data;
}

export async function deletePlaylist(id: number): Promise<void> {
    await apiClient.delete(`/playlists/${id}`);
}

export async function getPlaylistSongs(playlistId: number): Promise<Song[]> {
    const response = await apiClient.get<ApiResponse<Song[]>>(`/playlists/${playlistId}/songs`);
    return response.data.data;
}

export async function addSongsToPlaylist(playlistId: number, songIds: string[]): Promise<void> {
    await apiClient.post(`/playlists/${playlistId}/songs`, { song_ids: songIds });
}

export async function removeSongsFromPlaylist(playlistId: number, songIds: string[]): Promise<void> {
    await apiClient.delete(`/playlists/${playlistId}/songs`, { data: { song_ids: songIds } });
}

export async function reorderPlaylistSongs(playlistId: number, songIds: string[]): Promise<void> {
    await apiClient.put(`/playlists/${playlistId}/songs/reorder`, { song_ids: songIds });
}

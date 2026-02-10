import { apiClient } from './client';
import type { Song } from '@/types/models';
import type { ApiResponse, PaginatedResponse } from '@/types/api';

export interface SongFilters {
    search?: string;
    album_id?: number;
    artist_id?: number;
    page?: number;
    per_page?: number;
}

export async function getSongs(filters: SongFilters = {}): Promise<PaginatedResponse<Song>> {
    const response = await apiClient.get<PaginatedResponse<Song>>('/songs', { params: filters });
    return response.data;
}

export async function getSong(id: number): Promise<Song> {
    const response = await apiClient.get<ApiResponse<Song>>(`/songs/${id}`);
    return response.data.data;
}

export async function getSongBySlug(slug: string): Promise<Song> {
    const response = await apiClient.get<ApiResponse<Song>>(`/songs/${slug}`);
    return response.data.data;
}

export function getStreamUrl(songId: number): string {
    const token = localStorage.getItem('auth_token');
    if (!token) {
        throw new Error('Authentication token not found');
    }
    return `/api/v1/songs/${songId}/stream?token=${encodeURIComponent(token)}`;
}

export function getDownloadUrl(songId: number): string {
    const token = localStorage.getItem('auth_token');
    if (!token) {
        throw new Error('Authentication token not found');
    }
    return `/api/v1/songs/${songId}/download?token=${encodeURIComponent(token)}`;
}

export async function addTagsToSong(songId: number, tagIds: number[]): Promise<Song> {
    const response = await apiClient.post<ApiResponse<Song>>(`/songs/${songId}/tags`, { tag_ids: tagIds });
    return response.data.data;
}

export async function removeTagsFromSong(songId: number, tagIds: number[]): Promise<Song> {
    const response = await apiClient.delete<ApiResponse<Song>>(`/songs/${songId}/tags`, { data: { tag_ids: tagIds } });
    return response.data.data;
}

export async function getRecentlyPlayed(limit = 10): Promise<Song[]> {
    const response = await apiClient.get<{ data: Song[] }>('/songs/recently-played', {
        params: { limit },
    });
    return response.data.data;
}

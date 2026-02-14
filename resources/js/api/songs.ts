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

export async function getSong(id: string): Promise<Song> {
    const response = await apiClient.get<ApiResponse<Song>>(`/songs/${id}`);
    return response.data.data;
}

export async function getSongBySlug(slug: string): Promise<Song> {
    const response = await apiClient.get<ApiResponse<Song>>(`/songs/${slug}`);
    return response.data.data;
}

export interface StreamResponse {
    url: string;
    audio_format: string;
    audio_length: number;
}

export async function getStreamUrl(songId: string): Promise<string> {
    const response = await apiClient.get<{ data: StreamResponse }>(`/songs/${songId}/stream`);
    return response.data.data.url;
}

export function getDownloadUrl(songId: string): string {
    const token = localStorage.getItem('auth_token');
    if (!token) {
        throw new Error('Authentication token not found');
    }
    return `/api/v1/songs/${songId}/download?token=${encodeURIComponent(token)}`;
}

export async function addTagsToSong(songId: string, tagIds: number[]): Promise<Song> {
    const response = await apiClient.post<ApiResponse<Song>>(`/songs/${songId}/tags`, { tag_ids: tagIds });
    return response.data.data;
}

export async function removeTagsFromSong(songId: string, tagIds: number[]): Promise<Song> {
    const response = await apiClient.delete<ApiResponse<Song>>(`/songs/${songId}/tags`, { data: { tag_ids: tagIds } });
    return response.data.data;
}

export async function getRecentlyPlayed(limit = 10): Promise<Song[]> {
    const response = await apiClient.get<{ data: Song[] }>('/songs/recently-played', {
        params: { limit },
    });
    return response.data.data;
}

export interface UpdateSongData {
    title?: string;
    artist_name?: string | null;
    album_name?: string | null;
    year?: number | null;
    track?: number | null;
    disc?: number | null;
    genre?: string | null;
}

export async function updateSong(songId: string, data: UpdateSongData): Promise<Song> {
    const response = await apiClient.put<ApiResponse<Song>>(`/songs/${songId}`, data);
    return response.data.data;
}

export interface BulkUpdateSongsData extends Partial<UpdateSongData> {
    add_tag_ids?: number[];
    remove_tag_ids?: number[];
}

export async function bulkUpdateSongs(
    songIds: string[],
    data: BulkUpdateSongsData
): Promise<Song[]> {
    const response = await apiClient.put<{ data: Song[] }>('/songs/bulk', {
        song_ids: songIds,
        ...data,
    });
    return response.data.data;
}

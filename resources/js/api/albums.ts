import { apiClient } from './client';
import type { Album, Song } from '@/types/models';
import type { ApiResponse, PaginatedResponse } from '@/types/api';

export interface AlbumFilters {
    search?: string;
    artist_id?: number;
    page?: number;
    per_page?: number;
}

export async function getAlbums(filters: AlbumFilters = {}): Promise<PaginatedResponse<Album>> {
    const response = await apiClient.get<PaginatedResponse<Album>>('/albums', { params: filters });
    return response.data;
}

export async function getAlbum(id: number): Promise<Album> {
    const response = await apiClient.get<ApiResponse<Album>>(`/albums/${id}`);
    return response.data.data;
}

export async function getAlbumBySlug(slug: string): Promise<Album> {
    const response = await apiClient.get<ApiResponse<Album>>(`/albums/${slug}`);
    return response.data.data;
}

export async function getAlbumSongs(albumId: number): Promise<Song[]> {
    const response = await apiClient.get<ApiResponse<Song[]>>(`/albums/${albumId}/songs`);
    return response.data.data;
}

import { apiClient } from './client';
import type { Artist, Album, Song } from '@/types/models';
import type { ApiResponse, PaginatedResponse } from '@/types/api';

export interface ArtistFilters {
    search?: string;
    page?: number;
    per_page?: number;
}

export async function getArtists(filters: ArtistFilters = {}): Promise<PaginatedResponse<Artist>> {
    const response = await apiClient.get<PaginatedResponse<Artist>>('/artists', { params: filters });
    return response.data;
}

export async function getArtist(id: string): Promise<Artist> {
    const response = await apiClient.get<ApiResponse<Artist>>(`/artists/${id}`);
    return response.data.data;
}

export async function getArtistAlbums(artistId: string): Promise<Album[]> {
    const response = await apiClient.get<ApiResponse<Album[]>>(`/artists/${artistId}/albums`);
    return response.data.data;
}

export async function getArtistSongs(artistId: string): Promise<Song[]> {
    const response = await apiClient.get<ApiResponse<Song[]>>(`/artists/${artistId}/songs`);
    return response.data.data;
}

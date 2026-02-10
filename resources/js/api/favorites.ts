import { apiClient } from './client';
import type { Song } from '@/types/models';
import type { PaginatedResponse } from '@/types/api';

export interface FavoriteFilters {
    page?: number;
    per_page?: number;
}

export async function getFavorites(filters: FavoriteFilters = {}): Promise<PaginatedResponse<Song>> {
    const response = await apiClient.get<PaginatedResponse<Song>>('/favorites', { params: filters });
    return response.data;
}

export async function addFavorite(songId: number): Promise<void> {
    await apiClient.post('/favorites', { song_id: songId });
}

export async function removeFavorite(songId: number): Promise<void> {
    await apiClient.delete('/favorites', { data: { song_id: songId } });
}

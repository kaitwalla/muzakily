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

export async function addFavorite(songId: string): Promise<void> {
    await apiClient.post('/favorites', { type: 'song', id: songId });
}

export async function removeFavorite(songId: string): Promise<void> {
    await apiClient.delete('/favorites', { data: { type: 'song', id: songId } });
}

export async function toggleFavorite(songId: string, isFavorite: boolean): Promise<void> {
    if (isFavorite) {
        await removeFavorite(songId);
    } else {
        await addFavorite(songId);
    }
}

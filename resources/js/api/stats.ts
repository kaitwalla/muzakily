import { apiClient } from './client';

export interface LibraryStats {
    songs: number;
    albums: number;
    artists: number;
    playlists: number;
    total_duration: number;
    total_size: number;
}

export async function getStats(): Promise<LibraryStats> {
    const response = await apiClient.get<{ data: LibraryStats }>('/stats');
    return response.data.data;
}

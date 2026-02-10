import { apiClient } from './client';
import type { Song, Album, Artist, Playlist } from '@/types/models';

export interface SearchResults {
    songs: Song[];
    albums: Album[];
    artists: Artist[];
    playlists: Playlist[];
}

export interface SearchFilters {
    q: string;
    type?: 'all' | 'songs' | 'albums' | 'artists' | 'playlists';
    limit?: number;
}

export async function search(filters: SearchFilters): Promise<SearchResults> {
    const response = await apiClient.get<{ data: SearchResults }>('/search', { params: filters });
    return response.data.data;
}

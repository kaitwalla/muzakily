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

interface BackendSearchResults {
    songs?: { data: Song[]; total: number };
    albums?: { data: Album[]; total: number };
    artists?: { data: Artist[]; total: number };
    playlists?: { data: Playlist[]; total: number };
}

export async function search(filters: SearchFilters): Promise<SearchResults> {
    const response = await apiClient.get<{ data: BackendSearchResults }>('/search', { params: filters });
    const data = response.data.data;

    // Transform nested structure to flat arrays and provide defaults
    return {
        songs: data.songs?.data ?? [],
        albums: data.albums?.data ?? [],
        artists: data.artists?.data ?? [],
        playlists: data.playlists?.data ?? [],
    };
}

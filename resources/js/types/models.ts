export interface Artist {
    id: string;
    name: string;
    image: string | null;
    bio?: string | null;
    album_count: number;
    song_count: number;
    created_at: string;
}

export interface Album {
    id: string;
    name: string;
    artist_id: string | null;
    artist_name: string | null;
    cover: string | null;
    year: number | null;
    song_count: number;
    total_length: number;
    created_at: string;
}

export interface Song {
    id: string;
    title: string;
    artist_id: string | null;
    artist_name: string | null;
    artist_slug: string | null;
    album_id: string | null;
    album_name: string | null;
    album_slug: string | null;
    album_cover: string | null;
    length: number;
    track: number | null;
    disc: number | null;
    year: number | null;
    genre: string | null;
    audio_format: string;
    is_favorite: boolean;
    play_count: number;
    tags?: Array<{
        id: number;
        name: string;
        slug: string;
        color: string | null;
    }>;
    storage_path: string;
    created_at: string;
}

import type { SmartPlaylistRuleGroup } from '@/config/smartPlaylist';
export type { SmartPlaylistRuleGroup };

export interface Tag {
    id: number;
    name: string;
    slug: string;
    color: string | null;
    song_count: number;
    parent_id: number | null;
    children?: Tag[];
    created_at: string;
}

export interface Playlist {
    id: string;
    slug: string;
    name: string;
    description: string | null;
    cover_url: string | null;
    user_id: number;
    is_public: boolean;
    is_smart: boolean;
    rules?: SmartPlaylistRuleGroup[];
    songs_count?: number;
    total_length?: number;
    created_at: string;
    updated_at: string;
    songs?: Song[];
}

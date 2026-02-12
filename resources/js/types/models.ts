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
    smart_folder?: {
        id: number;
        name: string;
        path: string;
    };
    tags?: Array<{
        id: number;
        name: string;
        slug: string;
        color: string | null;
    }>;
    created_at: string;
}

import type { SmartPlaylistRuleGroup } from '@/config/smartPlaylist';
export type { SmartPlaylistRuleGroup };

export interface Playlist {
    id: number;
    name: string;
    slug: string;
    description: string | null;
    user_id: number;
    is_public: boolean;
    is_smart: boolean;
    rules?: SmartPlaylistRuleGroup[];
    cover_url: string | null;
    created_at: string;
    updated_at: string;
    songs?: Song[];
    songs_count?: number;
}

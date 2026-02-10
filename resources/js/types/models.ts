export interface Artist {
    id: number;
    name: string;
    slug: string;
    bio: string | null;
    image_url: string | null;
    created_at: string;
    updated_at: string;
    albums?: Album[];
    songs?: Song[];
}

export interface Album {
    id: number;
    title: string;
    slug: string;
    artist_id: number;
    release_date: string | null;
    cover_url: string | null;
    created_at: string;
    updated_at: string;
    artist?: Artist;
    songs?: Song[];
}

export interface Song {
    id: number;
    title: string;
    slug: string;
    artist_id: number;
    album_id: number | null;
    duration: number;
    track_number: number | null;
    audio_url: string;
    created_at: string;
    updated_at: string;
    artist?: Artist;
    album?: Album;
}

export interface Playlist {
    id: number;
    name: string;
    slug: string;
    description: string | null;
    user_id: number;
    is_public: boolean;
    cover_url: string | null;
    created_at: string;
    updated_at: string;
    songs?: Song[];
    songs_count?: number;
}

import type { Song, Artist, Album, Playlist } from '@/types/models';

export const createMockSong = (overrides: Partial<Song> = {}): Song => ({
    id: '1',
    title: 'Test Song',
    artist_id: '1',
    artist_name: 'Test Artist',
    artist_slug: 'test-artist',
    album_id: '1',
    album_name: 'Test Album',
    album_slug: 'test-album',
    album_cover: null,
    length: 180,
    track: 1,
    disc: 1,
    year: 2024,
    genre: 'Rock',
    audio_format: 'mp3',
    is_favorite: false,
    play_count: 0,
    created_at: '2024-01-01T00:00:00Z',
    ...overrides,
});

export const createMockArtist = (overrides: Partial<Artist> = {}): Artist => ({
    id: '1',
    name: 'Test Artist',
    image: null,
    bio: null,
    album_count: 5,
    song_count: 25,
    created_at: '2024-01-01T00:00:00Z',
    ...overrides,
});

export const createMockAlbum = (overrides: Partial<Album> = {}): Album => ({
    id: '1',
    name: 'Test Album',
    artist_id: '1',
    artist_name: 'Test Artist',
    cover: null,
    year: 2024,
    song_count: 10,
    total_length: 3600,
    created_at: '2024-01-01T00:00:00Z',
    ...overrides,
});

export const createMockPlaylist = (overrides: Partial<Playlist> = {}): Playlist => ({
    id: 1,
    name: 'Test Playlist',
    slug: 'test-playlist',
    description: 'A test playlist',
    user_id: 1,
    is_public: false,
    is_smart: false,
    cover_url: null,
    created_at: '2024-01-01T00:00:00Z',
    updated_at: '2024-01-01T00:00:00Z',
    songs: [],
    songs_count: 0,
    ...overrides,
});

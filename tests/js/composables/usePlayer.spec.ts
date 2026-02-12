import { describe, it, expect, beforeEach, vi } from 'vitest';
import { usePlayer } from '@/composables/usePlayer';
import { usePlayerStore } from '@/stores/player';
import type { Song } from '@/types/models';

const createMockSong = (overrides: Partial<Song> = {}): Song => ({
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

describe('usePlayer', () => {
    beforeEach(() => {
        // Reset the store state before each test for isolation
        const store = usePlayerStore();
        store.clearQueue();
        store.$patch({
            isPlaying: false,
            currentTime: 0,
            duration: 0,
            volume: 1,
            isMuted: false,
            isShuffled: false,
            repeatMode: 'off',
        });
    });

    describe('computed properties', () => {
        it('should return initial state correctly', () => {
            const player = usePlayer();

            expect(player.isPlaying.value).toBe(false);
            expect(player.currentSong.value).toBeNull();
            expect(player.currentTime.value).toBe(0);
            expect(player.duration.value).toBe(0);
            expect(player.volume.value).toBe(1);
            expect(player.isMuted.value).toBe(false);
            expect(player.isShuffled.value).toBe(false);
            expect(player.repeatMode.value).toBe('off');
        });

        it('should calculate progress correctly', () => {
            const store = usePlayerStore();
            store.currentTime = 30;
            store.duration = 180;

            const player = usePlayer();
            expect(player.progress.value).toBeCloseTo(16.67, 1);
        });

        it('should return 0 progress when duration is 0', () => {
            const store = usePlayerStore();
            store.currentTime = 0;
            store.duration = 0;

            const player = usePlayer();
            expect(player.progress.value).toBe(0);
        });

        it('should format current time correctly', () => {
            const store = usePlayerStore();
            store.currentTime = 125;

            const player = usePlayer();
            expect(player.formattedCurrentTime.value).toBe('2:05');
        });

        it('should format duration correctly', () => {
            const store = usePlayerStore();
            store.duration = 300;

            const player = usePlayer();
            expect(player.formattedDuration.value).toBe('5:00');
        });

        it('should format time remaining correctly', () => {
            const store = usePlayerStore();
            store.currentTime = 60;
            store.duration = 180;

            const player = usePlayer();
            expect(player.formattedTimeRemaining.value).toBe('-2:00');
        });
    });

    describe('playback controls', () => {
        it('should call store play method', () => {
            const store = usePlayerStore();
            const playSpy = vi.spyOn(store, 'play');

            const player = usePlayer();
            const songs = [createMockSong()];
            player.play(songs, 0);

            expect(playSpy).toHaveBeenCalledWith(songs, 0);
        });

        it('should call store playSong method', () => {
            const store = usePlayerStore();
            const playSongSpy = vi.spyOn(store, 'playSong');

            const player = usePlayer();
            const song = createMockSong();
            player.playSong(song);

            expect(playSongSpy).toHaveBeenCalledWith(song);
        });

        it('should call store togglePlayPause method', () => {
            const store = usePlayerStore();
            const toggleSpy = vi.spyOn(store, 'togglePlayPause');

            const player = usePlayer();
            player.togglePlayPause();

            expect(toggleSpy).toHaveBeenCalled();
        });

        it('should call store next method', () => {
            const store = usePlayerStore();
            const nextSpy = vi.spyOn(store, 'next');

            const player = usePlayer();
            player.next();

            expect(nextSpy).toHaveBeenCalled();
        });

        it('should call store previous method', () => {
            const store = usePlayerStore();
            const previousSpy = vi.spyOn(store, 'previous');

            const player = usePlayer();
            player.previous();

            expect(previousSpy).toHaveBeenCalled();
        });

        it('should seek to specific time', () => {
            const store = usePlayerStore();
            const seekSpy = vi.spyOn(store, 'seek');

            const player = usePlayer();
            player.seek(60);

            expect(seekSpy).toHaveBeenCalledWith(60);
        });

        it('should seek to percent of duration', () => {
            const store = usePlayerStore();
            store.duration = 200;
            const seekSpy = vi.spyOn(store, 'seek');

            const player = usePlayer();
            player.seekToPercent(50);

            expect(seekSpy).toHaveBeenCalledWith(100);
        });
    });

    describe('volume controls', () => {
        it('should set volume', () => {
            const store = usePlayerStore();
            const setVolumeSpy = vi.spyOn(store, 'setVolume');

            const player = usePlayer();
            player.setVolume(0.5);

            expect(setVolumeSpy).toHaveBeenCalledWith(0.5);
        });

        it('should toggle mute', () => {
            const store = usePlayerStore();
            const toggleMuteSpy = vi.spyOn(store, 'toggleMute');

            const player = usePlayer();
            player.toggleMute();

            expect(toggleMuteSpy).toHaveBeenCalled();
        });
    });

    describe('playback mode controls', () => {
        it('should toggle shuffle', () => {
            const store = usePlayerStore();
            const toggleShuffleSpy = vi.spyOn(store, 'toggleShuffle');

            const player = usePlayer();
            player.toggleShuffle();

            expect(toggleShuffleSpy).toHaveBeenCalled();
        });

        it('should cycle repeat mode', () => {
            const store = usePlayerStore();
            const cycleRepeatSpy = vi.spyOn(store, 'cycleRepeatMode');

            const player = usePlayer();
            player.cycleRepeatMode();

            expect(cycleRepeatSpy).toHaveBeenCalled();
        });
    });
});

import { computed, type ComputedRef } from 'vue';
import { usePlayerStore } from '@/stores/player';
import type { Song } from '@/types/models';
import type { RepeatMode } from '@/types/player';

export interface UsePlayerReturn {
    // State
    isPlaying: ComputedRef<boolean>;
    currentSong: ComputedRef<Song | null>;
    currentTime: ComputedRef<number>;
    duration: ComputedRef<number>;
    volume: ComputedRef<number>;
    isMuted: ComputedRef<boolean>;
    isShuffled: ComputedRef<boolean>;
    repeatMode: ComputedRef<RepeatMode>;
    hasNext: ComputedRef<boolean>;
    hasPrevious: ComputedRef<boolean>;
    progress: ComputedRef<number>;
    formattedCurrentTime: ComputedRef<string>;
    formattedDuration: ComputedRef<string>;
    formattedTimeRemaining: ComputedRef<string>;

    // Playback controls
    play: (songs: Song[], startIndex?: number) => void;
    playSong: (song: Song) => void;
    togglePlayPause: () => void;
    next: () => void;
    previous: () => void;
    seek: (time: number) => void;
    seekToPercent: (percent: number) => void;

    // Volume controls
    setVolume: (volume: number) => void;
    toggleMute: () => void;

    // Playback mode controls
    toggleShuffle: () => void;
    cycleRepeatMode: () => void;
    setRepeatMode: (mode: RepeatMode) => void;
}

/**
 * Format seconds to mm:ss string.
 */
function formatTime(seconds: number): string {
    if (!isFinite(seconds) || seconds < 0) return '0:00';

    const mins = Math.floor(seconds / 60);
    const secs = Math.floor(seconds % 60);
    return `${mins}:${secs.toString().padStart(2, '0')}`;
}

/**
 * Composable that wraps the player store with additional computed properties and utilities.
 */
export function usePlayer(): UsePlayerReturn {
    const playerStore = usePlayerStore();

    // Computed state
    const isPlaying = computed(() => playerStore.isPlaying);
    const currentSong = computed(() => playerStore.currentSong);
    const currentTime = computed(() => playerStore.currentTime);
    const duration = computed(() => playerStore.duration);
    const volume = computed(() => playerStore.volume);
    const isMuted = computed(() => playerStore.isMuted);
    const isShuffled = computed(() => playerStore.isShuffled);
    const repeatMode = computed(() => playerStore.repeatMode);
    const hasNext = computed(() => playerStore.hasNext);
    const hasPrevious = computed(() => playerStore.hasPrevious);

    // Derived computed
    const progress = computed(() => {
        if (duration.value === 0) return 0;
        return (currentTime.value / duration.value) * 100;
    });

    const formattedCurrentTime = computed(() => formatTime(currentTime.value));
    const formattedDuration = computed(() => formatTime(duration.value));
    const formattedTimeRemaining = computed(() => {
        const remaining = duration.value - currentTime.value;
        return `-${formatTime(remaining)}`;
    });

    // Playback controls
    function play(songs: Song[], startIndex = 0): void {
        playerStore.play(songs, startIndex);
    }

    function playSong(song: Song): void {
        playerStore.playSong(song);
    }

    function togglePlayPause(): void {
        playerStore.togglePlayPause();
    }

    function next(): void {
        playerStore.next();
    }

    function previous(): void {
        playerStore.previous();
    }

    function seek(time: number): void {
        playerStore.seek(time);
    }

    function seekToPercent(percent: number): void {
        const time = (percent / 100) * duration.value;
        playerStore.seek(time);
    }

    // Volume controls
    function setVolume(newVolume: number): void {
        playerStore.setVolume(newVolume);
    }

    function toggleMute(): void {
        playerStore.toggleMute();
    }

    // Playback mode controls
    function toggleShuffle(): void {
        playerStore.toggleShuffle();
    }

    function cycleRepeatMode(): void {
        playerStore.cycleRepeatMode();
    }

    function setRepeatMode(mode: RepeatMode): void {
        playerStore.setRepeatMode(mode);
    }

    return {
        // State
        isPlaying,
        currentSong,
        currentTime,
        duration,
        volume,
        isMuted,
        isShuffled,
        repeatMode,
        hasNext,
        hasPrevious,
        progress,
        formattedCurrentTime,
        formattedDuration,
        formattedTimeRemaining,

        // Playback controls
        play,
        playSong,
        togglePlayPause,
        next,
        previous,
        seek,
        seekToPercent,

        // Volume controls
        setVolume,
        toggleMute,

        // Playback mode controls
        toggleShuffle,
        cycleRepeatMode,
        setRepeatMode,
    };
}

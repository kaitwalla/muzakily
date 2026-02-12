import { ref, onMounted, onUnmounted, watch, type Ref } from 'vue';
import { usePlayerStore } from '@/stores/player';

export interface UseAudioOptions {
    autoRegister?: boolean;
}

export interface UseAudioReturn {
    audioRef: Ref<HTMLAudioElement | null>;
    isReady: Ref<boolean>;
    error: Ref<string | null>;
    registerAudioElement: () => void;
    unregisterAudioElement: () => void;
}

/**
 * Composable for managing the audio element and media session API.
 */
export function useAudio(options: UseAudioOptions = {}): UseAudioReturn {
    const { autoRegister = true } = options;

    const playerStore = usePlayerStore();
    const audioRef = ref<HTMLAudioElement | null>(null);
    const isReady = ref(false);
    const error = ref<string | null>(null);

    function registerAudioElement(): void {
        if (audioRef.value) {
            playerStore.setAudioElement(audioRef.value);
            setupMediaSession();
            isReady.value = true;
        }
    }

    function unregisterAudioElement(): void {
        isReady.value = false;
    }

    function setupMediaSession(): void {
        if (!('mediaSession' in navigator)) return;

        navigator.mediaSession.setActionHandler('play', () => {
            playerStore.resume();
        });

        navigator.mediaSession.setActionHandler('pause', () => {
            playerStore.pause();
        });

        navigator.mediaSession.setActionHandler('previoustrack', () => {
            playerStore.previous();
        });

        navigator.mediaSession.setActionHandler('nexttrack', () => {
            playerStore.next();
        });

        navigator.mediaSession.setActionHandler('seekto', (details) => {
            if (details.seekTime !== undefined) {
                playerStore.seek(details.seekTime);
            }
        });

        navigator.mediaSession.setActionHandler('seekbackward', (details) => {
            const skipTime = details.seekOffset || 10;
            playerStore.seek(Math.max(0, playerStore.currentTime - skipTime));
        });

        navigator.mediaSession.setActionHandler('seekforward', (details) => {
            const skipTime = details.seekOffset || 10;
            playerStore.seek(Math.min(playerStore.duration, playerStore.currentTime + skipTime));
        });
    }

    function updateMediaSessionMetadata(): void {
        if (!('mediaSession' in navigator) || !playerStore.currentSong) return;

        const song = playerStore.currentSong;
        const artwork: MediaImage[] = [];

        if (song.album_cover) {
            artwork.push({
                src: song.album_cover,
                sizes: '512x512',
                type: 'image/jpeg',
            });
        }

        navigator.mediaSession.metadata = new MediaMetadata({
            title: song.title,
            artist: song.artist_name ?? 'Unknown Artist',
            album: song.album_name ?? 'Unknown Album',
            artwork,
        });
    }

    function updateMediaSessionPlaybackState(): void {
        if (!('mediaSession' in navigator)) return;

        navigator.mediaSession.playbackState = playerStore.isPlaying ? 'playing' : 'paused';
    }

    function updateMediaSessionPosition(): void {
        if (!('mediaSession' in navigator) || !navigator.mediaSession.setPositionState) return;
        if (playerStore.duration <= 0) return;

        navigator.mediaSession.setPositionState({
            duration: playerStore.duration,
            playbackRate: 1,
            position: playerStore.currentTime,
        });
    }

    // Watch for changes to update media session
    watch(
        () => playerStore.currentSong,
        () => {
            updateMediaSessionMetadata();
        }
    );

    watch(
        () => playerStore.isPlaying,
        () => {
            updateMediaSessionPlaybackState();
        }
    );

    watch(
        () => playerStore.currentTime,
        () => {
            // Only update position state every 5 seconds to avoid too many updates
            if (Math.floor(playerStore.currentTime) % 5 === 0) {
                updateMediaSessionPosition();
            }
        }
    );

    onMounted(() => {
        if (autoRegister && audioRef.value) {
            registerAudioElement();
        }
    });

    onUnmounted(() => {
        unregisterAudioElement();
    });

    return {
        audioRef,
        isReady,
        error,
        registerAudioElement,
        unregisterAudioElement,
    };
}

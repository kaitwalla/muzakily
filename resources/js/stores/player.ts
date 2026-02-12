import { defineStore } from 'pinia';
import { ref, computed } from 'vue';
import type { Song } from '@/types/models';
import type { PlaybackState, QueueItem, RepeatMode } from '@/types/player';
import * as interactionsApi from '@/api/interactions';
import * as songsApi from '@/api/songs';

export const usePlayerStore = defineStore('player', () => {
    // Queue state
    const queue = ref<QueueItem[]>([]);
    const currentIndex = ref(-1);
    const originalQueue = ref<QueueItem[]>([]);

    // Playback state
    const isPlaying = ref(false);
    const currentTime = ref(0);
    const duration = ref(0);
    const volume = ref(1);
    const isMuted = ref(false);
    const isShuffled = ref(false);
    const repeatMode = ref<RepeatMode>('off');

    // Audio element reference
    let audioElement: HTMLAudioElement | null = null;

    // Store bound handlers for cleanup
    type EventHandler = () => void;
    const boundHandlers: { event: string; handler: EventHandler }[] = [];

    // Computed
    const currentSong = computed<Song | null>(() => {
        if (currentIndex.value >= 0 && currentIndex.value < queue.value.length) {
            return queue.value[currentIndex.value].song;
        }
        return null;
    });

    const hasNext = computed(() => {
        if (repeatMode.value === 'all') return queue.value.length > 0;
        return currentIndex.value < queue.value.length - 1;
    });

    const hasPrevious = computed(() => {
        if (repeatMode.value === 'all') return queue.value.length > 0;
        return currentIndex.value > 0;
    });

    const playbackState = computed<PlaybackState>(() => ({
        isPlaying: isPlaying.value,
        currentTime: currentTime.value,
        duration: duration.value,
        volume: volume.value,
        isMuted: isMuted.value,
        isShuffled: isShuffled.value,
        repeatMode: repeatMode.value,
    }));

    // Methods
    function setAudioElement(element: HTMLAudioElement): void {
        // Clean up previous listeners
        if (audioElement && boundHandlers.length > 0) {
            boundHandlers.forEach(({ event, handler }) => {
                audioElement?.removeEventListener(event, handler);
            });
            boundHandlers.length = 0;
        }

        audioElement = element;
        if (!audioElement) return;

        const onTimeUpdate = (): void => {
            currentTime.value = audioElement?.currentTime ?? 0;
        };
        const onDurationChange = (): void => {
            duration.value = audioElement?.duration ?? 0;
        };
        const onPlay = (): void => {
            isPlaying.value = true;
        };
        const onPause = (): void => {
            isPlaying.value = false;
        };

        audioElement.addEventListener('timeupdate', onTimeUpdate);
        audioElement.addEventListener('durationchange', onDurationChange);
        audioElement.addEventListener('ended', handleTrackEnd);
        audioElement.addEventListener('play', onPlay);
        audioElement.addEventListener('pause', onPause);

        boundHandlers.push(
            { event: 'timeupdate', handler: onTimeUpdate },
            { event: 'durationchange', handler: onDurationChange },
            { event: 'ended', handler: handleTrackEnd },
            { event: 'play', handler: onPlay },
            { event: 'pause', handler: onPause },
        );
    }

    function handleTrackEnd(): void {
        if (repeatMode.value === 'one') {
            if (audioElement) {
                audioElement.currentTime = 0;
                audioElement.play().catch(() => {
                    isPlaying.value = false;
                });
            }
        } else if (hasNext.value) {
            next();
        } else {
            isPlaying.value = false;
        }
    }

    function generateQueueId(): string {
        return Math.random().toString(36).substring(2, 11);
    }

    function play(songs: Song[], startIndex = 0): void {
        queue.value = songs.map((song) => ({
            id: generateQueueId(),
            song,
        }));
        originalQueue.value = [...queue.value];
        currentIndex.value = startIndex;

        if (isShuffled.value) {
            shuffleQueue();
        }

        void loadCurrentSong();
    }

    function playSong(song: Song): void {
        play([song], 0);
    }

    function addToQueue(song: Song): void {
        queue.value.push({
            id: generateQueueId(),
            song,
        });
    }

    function insertAfterCurrent(song: Song): void {
        const queueItem: QueueItem = {
            id: generateQueueId(),
            song,
        };
        const insertIndex = currentIndex.value + 1;
        queue.value.splice(insertIndex, 0, queueItem);
    }

    function moveQueueItem(fromIndex: number, toIndex: number): void {
        if (fromIndex === toIndex) return;
        if (fromIndex < 0 || fromIndex >= queue.value.length) return;
        if (toIndex < 0 || toIndex >= queue.value.length) return;

        const [removed] = queue.value.splice(fromIndex, 1);
        queue.value.splice(toIndex, 0, removed);

        // Adjust currentIndex if needed
        if (fromIndex === currentIndex.value) {
            currentIndex.value = toIndex;
        } else if (fromIndex < currentIndex.value && toIndex >= currentIndex.value) {
            currentIndex.value--;
        } else if (fromIndex > currentIndex.value && toIndex <= currentIndex.value) {
            currentIndex.value++;
        }
    }

    function removeFromQueue(queueItemId: string): void {
        const index = queue.value.findIndex((item) => item.id === queueItemId);
        if (index === -1) return;

        queue.value.splice(index, 1);

        if (index < currentIndex.value) {
            currentIndex.value--;
        } else if (index === currentIndex.value) {
            if (queue.value.length === 0) {
                clearQueue();
            } else {
                if (currentIndex.value >= queue.value.length) {
                    currentIndex.value = queue.value.length - 1;
                }
                void loadCurrentSong();
            }
        }
    }

    function clearQueue(): void {
        queue.value = [];
        originalQueue.value = [];
        currentIndex.value = -1;
        if (audioElement) {
            audioElement.pause();
            audioElement.src = '';
        }
        isPlaying.value = false;
    }

    async function loadCurrentSong(): Promise<void> {
        if (!audioElement || !currentSong.value) return;

        try {
            const streamUrl = await songsApi.getStreamUrl(currentSong.value.id);
            audioElement.src = streamUrl;
            audioElement.load();
            await audioElement.play();
        } catch {
            isPlaying.value = false;
        }

        // Record the play interaction
        interactionsApi.recordPlay(currentSong.value.id).catch(() => {
            // Silently fail - don't interrupt playback for analytics
        });
    }

    function togglePlayPause(): void {
        if (!audioElement) return;

        if (isPlaying.value) {
            audioElement.pause();
        } else {
            audioElement.play().catch(() => {
                isPlaying.value = false;
            });
        }
    }

    function resume(): void {
        if (!audioElement) return;
        audioElement.play().catch(() => {
            isPlaying.value = false;
        });
    }

    function pause(): void {
        if (!audioElement) return;
        audioElement.pause();
    }

    function next(): void {
        if (queue.value.length === 0) return;

        if (currentIndex.value < queue.value.length - 1) {
            currentIndex.value++;
        } else if (repeatMode.value === 'all') {
            currentIndex.value = 0;
        } else {
            return;
        }

        void loadCurrentSong();
    }

    function previous(): void {
        if (queue.value.length === 0) return;

        // If more than 3 seconds into song, restart it
        if (currentTime.value > 3) {
            seek(0);
            return;
        }

        if (currentIndex.value > 0) {
            currentIndex.value--;
        } else if (repeatMode.value === 'all') {
            currentIndex.value = queue.value.length - 1;
        } else {
            return;
        }

        void loadCurrentSong();
    }

    function seek(time: number): void {
        if (audioElement) {
            audioElement.currentTime = time;
        }
    }

    function setVolume(newVolume: number): void {
        volume.value = Math.max(0, Math.min(1, newVolume));
        if (audioElement) {
            audioElement.volume = volume.value;
        }
        if (volume.value > 0) {
            isMuted.value = false;
        }
    }

    function toggleMute(): void {
        isMuted.value = !isMuted.value;
        if (audioElement) {
            audioElement.muted = isMuted.value;
        }
    }

    function toggleShuffle(): void {
        isShuffled.value = !isShuffled.value;

        if (isShuffled.value) {
            shuffleQueue();
        } else {
            // Restore original order
            const currentSongId = queue.value[currentIndex.value]?.id;
            queue.value = [...originalQueue.value];
            currentIndex.value = queue.value.findIndex((item) => item.id === currentSongId);
            if (currentIndex.value === -1) currentIndex.value = 0;
        }
    }

    function shuffleQueue(): void {
        if (queue.value.length <= 1) return;

        const current = queue.value[currentIndex.value];
        const others = queue.value.filter((_, i) => i !== currentIndex.value);

        // Fisher-Yates shuffle
        for (let i = others.length - 1; i > 0; i--) {
            const j = Math.floor(Math.random() * (i + 1));
            [others[i], others[j]] = [others[j], others[i]];
        }

        queue.value = [current, ...others];
        currentIndex.value = 0;
    }

    function cycleRepeatMode(): void {
        const modes: RepeatMode[] = ['off', 'all', 'one'];
        const currentModeIndex = modes.indexOf(repeatMode.value);
        repeatMode.value = modes[(currentModeIndex + 1) % modes.length];
    }

    function setRepeatMode(mode: RepeatMode): void {
        repeatMode.value = mode;
    }

    return {
        // State
        queue,
        currentIndex,
        isPlaying,
        currentTime,
        duration,
        volume,
        isMuted,
        isShuffled,
        repeatMode,

        // Computed
        currentSong,
        hasNext,
        hasPrevious,
        playbackState,

        // Methods
        setAudioElement,
        play,
        playSong,
        addToQueue,
        insertAfterCurrent,
        moveQueueItem,
        removeFromQueue,
        clearQueue,
        togglePlayPause,
        resume,
        pause,
        next,
        previous,
        seek,
        setVolume,
        toggleMute,
        toggleShuffle,
        cycleRepeatMode,
        setRepeatMode,
    };
});

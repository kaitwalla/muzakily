import { computed, type ComputedRef } from 'vue';
import { usePlayerStore } from '@/stores/player';
import type { Song } from '@/types/models';
import type { QueueItem } from '@/types/player';

export interface UseQueueReturn {
    queue: ComputedRef<QueueItem[]>;
    currentIndex: ComputedRef<number>;
    currentItem: ComputedRef<QueueItem | null>;
    isEmpty: ComputedRef<boolean>;
    length: ComputedRef<number>;
    upNext: ComputedRef<QueueItem[]>;
    addToQueue: (song: Song) => void;
    addMultipleToQueue: (songs: Song[]) => void;
    playNext: (song: Song) => void;
    removeFromQueue: (queueItemId: string) => void;
    clearQueue: () => void;
    moveInQueue: (fromIndex: number, toIndex: number) => void;
    jumpToIndex: (index: number) => void;
}

/**
 * Composable for queue management utilities.
 */
export function useQueue(): UseQueueReturn {
    const playerStore = usePlayerStore();

    const queue = computed(() => playerStore.queue);
    const currentIndex = computed(() => playerStore.currentIndex);

    const currentItem = computed<QueueItem | null>(() => {
        if (currentIndex.value >= 0 && currentIndex.value < queue.value.length) {
            return queue.value[currentIndex.value];
        }
        return null;
    });

    const isEmpty = computed(() => queue.value.length === 0);
    const length = computed(() => queue.value.length);

    const upNext = computed(() => {
        if (currentIndex.value < 0) return queue.value;
        return queue.value.slice(currentIndex.value + 1);
    });

    function addToQueue(song: Song): void {
        playerStore.addToQueue(song);
    }

    function addMultipleToQueue(songs: Song[]): void {
        songs.forEach((song) => playerStore.addToQueue(song));
    }

    function playNext(song: Song): void {
        playerStore.insertAfterCurrent(song);
    }

    function removeFromQueue(queueItemId: string): void {
        playerStore.removeFromQueue(queueItemId);
    }

    function clearQueue(): void {
        playerStore.clearQueue();
    }

    function moveInQueue(fromIndex: number, toIndex: number): void {
        playerStore.moveQueueItem(fromIndex, toIndex);
    }

    function jumpToIndex(index: number): void {
        if (index < 0 || index >= queue.value.length) return;

        // Play the songs starting from the selected index
        const songs = queue.value.map((item) => item.song);
        playerStore.play(songs, index);
    }

    return {
        queue,
        currentIndex,
        currentItem,
        isEmpty,
        length,
        upNext,
        addToQueue,
        addMultipleToQueue,
        playNext,
        removeFromQueue,
        clearQueue,
        moveInQueue,
        jumpToIndex,
    };
}

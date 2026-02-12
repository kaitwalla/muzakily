import { describe, it, expect, beforeEach, vi } from 'vitest';
import { useQueue } from '@/composables/useQueue';
import { usePlayerStore } from '@/stores/player';
import { createMockSong } from '../utils/test-helpers';

describe('useQueue', () => {
    beforeEach(() => {
        const store = usePlayerStore();
        store.clearQueue();
    });

    describe('computed properties', () => {
        it('should return queue from store', () => {
            const store = usePlayerStore();
            const song = createMockSong();
            store.play([song], 0);

            const { queue } = useQueue();

            expect(queue.value).toHaveLength(1);
            expect(queue.value[0].song).toEqual(song);
        });

        it('should return currentIndex from store', () => {
            const store = usePlayerStore();
            store.play([createMockSong(), createMockSong({ id: '2' })], 1);

            const { currentIndex } = useQueue();

            expect(currentIndex.value).toBe(1);
        });

        it('should return currentItem correctly', () => {
            const store = usePlayerStore();
            const songs = [createMockSong({ id: '1' }), createMockSong({ id: '2' })];
            store.play(songs, 1);

            const { currentItem } = useQueue();

            expect(currentItem.value?.song.id).toBe('2');
        });

        it('should return null currentItem when queue is empty', () => {
            const { currentItem } = useQueue();
            expect(currentItem.value).toBeNull();
        });

        it('should return isEmpty true when queue is empty', () => {
            const { isEmpty } = useQueue();
            expect(isEmpty.value).toBe(true);
        });

        it('should return isEmpty false when queue has items', () => {
            const store = usePlayerStore();
            store.play([createMockSong()], 0);

            const { isEmpty } = useQueue();
            expect(isEmpty.value).toBe(false);
        });

        it('should return correct length', () => {
            const store = usePlayerStore();
            store.play([createMockSong(), createMockSong({ id: '2' }), createMockSong({ id: '3' })], 0);

            const { length } = useQueue();
            expect(length.value).toBe(3);
        });

        it('should return upNext excluding current and previous', () => {
            const store = usePlayerStore();
            const songs = [
                createMockSong({ id: '1', title: 'Song 1' }),
                createMockSong({ id: '2', title: 'Song 2' }),
                createMockSong({ id: '3', title: 'Song 3' }),
            ];
            store.play(songs, 1);

            const { upNext } = useQueue();

            expect(upNext.value).toHaveLength(1);
            expect(upNext.value[0].song.id).toBe('3');
        });

        it('should return full queue for upNext when currentIndex is -1', () => {
            const store = usePlayerStore();
            store.play([createMockSong({ id: '1' }), createMockSong({ id: '2' })], 0);
            store.$patch({ currentIndex: -1 });

            const { queue, upNext } = useQueue();
            expect(queue.value).toHaveLength(2);
            expect(upNext.value).toEqual(queue.value);
        });
    });

    describe('addToQueue', () => {
        it('should add song to end of queue', () => {
            const store = usePlayerStore();
            store.play([createMockSong({ id: '1' })], 0);

            const { addToQueue, queue } = useQueue();
            addToQueue(createMockSong({ id: '2', title: 'New Song' }));

            expect(queue.value).toHaveLength(2);
            expect(queue.value[1].song.title).toBe('New Song');
        });
    });

    describe('addMultipleToQueue', () => {
        it('should add multiple songs to queue', () => {
            const store = usePlayerStore();
            store.play([createMockSong({ id: '1' })], 0);

            const { addMultipleToQueue, queue } = useQueue();
            addMultipleToQueue([
                createMockSong({ id: '2', title: 'Song 2' }),
                createMockSong({ id: '3', title: 'Song 3' }),
            ]);

            expect(queue.value).toHaveLength(3);
        });
    });

    describe('playNext', () => {
        it('should insert song after current', () => {
            const store = usePlayerStore();
            store.play([
                createMockSong({ id: '1', title: 'Song 1' }),
                createMockSong({ id: '3', title: 'Song 3' }),
            ], 0);

            const { playNext, queue } = useQueue();
            playNext(createMockSong({ id: '2', title: 'Song 2' }));

            expect(queue.value).toHaveLength(3);
            expect(queue.value[1].song.title).toBe('Song 2');
        });
    });

    describe('removeFromQueue', () => {
        it('should remove item by queue ID', () => {
            const store = usePlayerStore();
            store.play([
                createMockSong({ id: '1', title: 'Song 1' }),
                createMockSong({ id: '2', title: 'Song 2' }),
            ], 0);

            const { removeFromQueue, queue } = useQueue();
            const queueIdToRemove = queue.value[1].id;
            removeFromQueue(queueIdToRemove);

            expect(queue.value).toHaveLength(1);
            expect(queue.value[0].song.title).toBe('Song 1');
        });
    });

    describe('clearQueue', () => {
        it('should clear all items from queue', () => {
            const store = usePlayerStore();
            store.play([createMockSong(), createMockSong({ id: '2' })], 0);

            const { clearQueue, queue, isEmpty } = useQueue();
            clearQueue();

            expect(queue.value).toHaveLength(0);
            expect(isEmpty.value).toBe(true);
        });
    });

    describe('moveInQueue', () => {
        it('should move item from one position to another', () => {
            const store = usePlayerStore();
            store.play([
                createMockSong({ id: '1', title: 'Song 1' }),
                createMockSong({ id: '2', title: 'Song 2' }),
                createMockSong({ id: '3', title: 'Song 3' }),
            ], 0);

            const { moveInQueue, queue } = useQueue();
            moveInQueue(2, 0);

            expect(queue.value[0].song.title).toBe('Song 3');
            expect(queue.value[1].song.title).toBe('Song 1');
            expect(queue.value[2].song.title).toBe('Song 2');
        });
    });

    describe('jumpToIndex', () => {
        it('should jump to specified index', () => {
            const store = usePlayerStore();
            const playSpy = vi.spyOn(store, 'play');
            store.play([
                createMockSong({ id: '1' }),
                createMockSong({ id: '2' }),
                createMockSong({ id: '3' }),
            ], 0);

            const { jumpToIndex } = useQueue();
            jumpToIndex(2);

            // Last call to play should have index 2
            const lastCall = playSpy.mock.calls[playSpy.mock.calls.length - 1];
            expect(lastCall[1]).toBe(2);
        });

        it('should not jump to invalid index', () => {
            const store = usePlayerStore();
            store.play([createMockSong()], 0);
            const playSpy = vi.spyOn(store, 'play');
            playSpy.mockClear();

            const { jumpToIndex } = useQueue();
            jumpToIndex(5); // Invalid index

            expect(playSpy).not.toHaveBeenCalled();
        });

        it('should not jump to negative index', () => {
            const store = usePlayerStore();
            store.play([createMockSong()], 0);
            const playSpy = vi.spyOn(store, 'play');
            playSpy.mockClear();

            const { jumpToIndex } = useQueue();
            jumpToIndex(-1);

            expect(playSpy).not.toHaveBeenCalled();
        });
    });
});

import { describe, it, expect, beforeEach } from 'vitest';
import { mount } from '@vue/test-utils';
import { createPinia, setActivePinia } from 'pinia';
import QueuePanel from '@/components/player/QueuePanel.vue';
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

describe('QueuePanel', () => {
    beforeEach(() => {
        setActivePinia(createPinia());
    });

    it('should render the queue panel', () => {
        const wrapper = mount(QueuePanel);
        expect(wrapper.find('h3').text()).toBe('Queue');
    });

    it('should show empty state when queue is empty', () => {
        const wrapper = mount(QueuePanel);
        expect(wrapper.text()).toContain('Queue is empty');
        expect(wrapper.text()).toContain('Add songs to get started');
    });

    it('should not show clear button when queue is empty', () => {
        const wrapper = mount(QueuePanel);
        expect(wrapper.text()).not.toContain('Clear');
    });

    it('should display songs in the queue', async () => {
        const store = usePlayerStore();
        const songs = [
            createMockSong({ id: '1', title: 'Song One' }),
            createMockSong({ id: '2', title: 'Song Two' }),
        ];
        store.play(songs, 0);

        const wrapper = mount(QueuePanel);

        expect(wrapper.text()).toContain('Song One');
        expect(wrapper.text()).toContain('Song Two');
    });

    it('should show clear button when queue has songs', () => {
        const store = usePlayerStore();
        store.play([createMockSong()], 0);

        const wrapper = mount(QueuePanel);
        expect(wrapper.text()).toContain('Clear');
    });

    it('should highlight the current playing song', () => {
        const store = usePlayerStore();
        const songs = [
            createMockSong({ id: '1', title: 'Song One' }),
            createMockSong({ id: '2', title: 'Song Two' }),
        ];
        store.play(songs, 0);

        const wrapper = mount(QueuePanel);
        const rows = wrapper.findAll('[draggable="true"]');

        // First song should have the active class
        expect(rows[0].classes()).toContain('bg-gray-700/50');
    });

    it('should emit close event when close button is clicked', async () => {
        const wrapper = mount(QueuePanel);
        const closeButton = wrapper.find('[aria-label="Close queue"]');

        await closeButton.trigger('click');

        expect(wrapper.emitted('close')).toHaveLength(1);
    });

    it('should clear the queue when clear button is clicked', async () => {
        const store = usePlayerStore();
        store.play([createMockSong()], 0);

        const wrapper = mount(QueuePanel);

        // Find clear button by text content
        const buttons = wrapper.findAll('button');
        const clearBtn = buttons.find((b) => b.text() === 'Clear');

        expect(clearBtn).toBeDefined();
        await clearBtn!.trigger('click');
        expect(store.queue.length).toBe(0);
    });

    it('should display song duration', () => {
        const store = usePlayerStore();
        store.play([createMockSong({ length: 185 })], 0);

        const wrapper = mount(QueuePanel);
        expect(wrapper.text()).toContain('3:05');
    });

    it('should display artist name', () => {
        const store = usePlayerStore();
        store.play([createMockSong()], 0);

        const wrapper = mount(QueuePanel);
        expect(wrapper.text()).toContain('Test Artist');
    });

    it('should display "Unknown Artist" when artist is missing', () => {
        const store = usePlayerStore();
        const song = createMockSong({ artist_name: null });
        store.play([song], 0);

        const wrapper = mount(QueuePanel);
        expect(wrapper.text()).toContain('Unknown Artist');
    });

    it('should have draggable items for reordering', () => {
        const store = usePlayerStore();
        store.play([createMockSong(), createMockSong({ id: '2' })], 0);

        const wrapper = mount(QueuePanel);
        const draggableItems = wrapper.findAll('[draggable="true"]');

        expect(draggableItems.length).toBe(2);
    });

    it('should have remove button for each song', () => {
        const store = usePlayerStore();
        store.play([createMockSong()], 0);

        const wrapper = mount(QueuePanel);
        const removeButton = wrapper.find('[aria-label="Remove from queue"]');

        expect(removeButton.exists()).toBe(true);
    });

    it('should remove song from queue when remove button is clicked', async () => {
        const store = usePlayerStore();
        const songs = [
            createMockSong({ id: '1', title: 'Song One' }),
            createMockSong({ id: '2', title: 'Song Two' }),
        ];
        store.play(songs, 0);

        expect(store.queue.length).toBe(2);

        const wrapper = mount(QueuePanel);
        const removeButtons = wrapper.findAll('[aria-label="Remove from queue"]');

        // Remove the second song
        await removeButtons[1].trigger('click');

        expect(store.queue.length).toBe(1);
        expect(store.queue[0].song.title).toBe('Song One');
    });
});

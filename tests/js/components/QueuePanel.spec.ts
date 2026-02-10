import { describe, it, expect, beforeEach } from 'vitest';
import { mount } from '@vue/test-utils';
import { createPinia, setActivePinia } from 'pinia';
import QueuePanel from '@/components/player/QueuePanel.vue';
import { usePlayerStore } from '@/stores/player';
import type { Song } from '@/types/models';

const createMockSong = (overrides: Partial<Song> = {}): Song => ({
    id: 1,
    title: 'Test Song',
    slug: 'test-song',
    artist_id: 1,
    album_id: 1,
    duration: 180,
    track_number: 1,
    audio_url: 'https://example.com/song.mp3',
    created_at: '2024-01-01T00:00:00Z',
    updated_at: '2024-01-01T00:00:00Z',
    artist: {
        id: 1,
        name: 'Test Artist',
        slug: 'test-artist',
        bio: null,
        image_url: null,
        created_at: '2024-01-01T00:00:00Z',
        updated_at: '2024-01-01T00:00:00Z',
    },
    album: {
        id: 1,
        title: 'Test Album',
        slug: 'test-album',
        artist_id: 1,
        release_date: '2024-01-01',
        cover_url: null,
        created_at: '2024-01-01T00:00:00Z',
        updated_at: '2024-01-01T00:00:00Z',
    },
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
            createMockSong({ id: 1, title: 'Song One' }),
            createMockSong({ id: 2, title: 'Song Two' }),
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
            createMockSong({ id: 1, title: 'Song One' }),
            createMockSong({ id: 2, title: 'Song Two' }),
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
        store.play([createMockSong({ duration: 185 })], 0);

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
        const song = createMockSong();
        song.artist = undefined;
        store.play([song], 0);

        const wrapper = mount(QueuePanel);
        expect(wrapper.text()).toContain('Unknown Artist');
    });

    it('should have draggable items for reordering', () => {
        const store = usePlayerStore();
        store.play([createMockSong(), createMockSong({ id: 2 })], 0);

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
            createMockSong({ id: 1, title: 'Song One' }),
            createMockSong({ id: 2, title: 'Song Two' }),
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

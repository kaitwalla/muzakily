import { describe, it, expect, vi, beforeEach } from 'vitest';
import { mount } from '@vue/test-utils';
import { createPinia, setActivePinia } from 'pinia';
import AudioPlayer from '@/components/player/AudioPlayer.vue';
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
    album_cover: 'https://example.com/cover.jpg',
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

describe('AudioPlayer', () => {
    beforeEach(() => {
        setActivePinia(createPinia());
    });

    it('should render the audio player', () => {
        const wrapper = mount(AudioPlayer);
        expect(wrapper.find('footer').exists()).toBe(true);
    });

    it('should contain an audio element', () => {
        const wrapper = mount(AudioPlayer);
        expect(wrapper.find('audio').exists()).toBe(true);
    });

    it('should display "No song playing" when queue is empty', () => {
        const wrapper = mount(AudioPlayer);
        expect(wrapper.text()).toContain('No song playing');
    });

    it('should display current song info when playing', async () => {
        const store = usePlayerStore();
        const song = createMockSong();
        store.play([song], 0);

        const wrapper = mount(AudioPlayer);

        expect(wrapper.text()).toContain('Test Song');
        expect(wrapper.text()).toContain('Test Artist');
    });

    it('should have shuffle button', () => {
        const wrapper = mount(AudioPlayer);
        const shuffleButton = wrapper.find('[aria-label="Shuffle"]');
        expect(shuffleButton.exists()).toBe(true);
    });

    it('should toggle shuffle when shuffle button is clicked', async () => {
        const store = usePlayerStore();
        const wrapper = mount(AudioPlayer);

        expect(store.isShuffled).toBe(false);

        const shuffleButton = wrapper.find('[aria-label="Shuffle"]');
        await shuffleButton.trigger('click');

        expect(store.isShuffled).toBe(true);
    });

    it('should have previous track button', () => {
        const wrapper = mount(AudioPlayer);
        const prevButton = wrapper.find('[aria-label="Previous track"]');
        expect(prevButton.exists()).toBe(true);
    });

    it('should have next track button', () => {
        const wrapper = mount(AudioPlayer);
        const nextButton = wrapper.find('[aria-label="Next track"]');
        expect(nextButton.exists()).toBe(true);
    });

    it('should have repeat button', () => {
        const wrapper = mount(AudioPlayer);
        const repeatButton = wrapper.find('[aria-label="Repeat off"]');
        expect(repeatButton.exists()).toBe(true);
    });

    it('should cycle repeat mode when repeat button is clicked', async () => {
        const store = usePlayerStore();
        const wrapper = mount(AudioPlayer);

        expect(store.repeatMode).toBe('off');

        const repeatButton = wrapper.find('[aria-label="Repeat off"]');
        await repeatButton.trigger('click');

        expect(store.repeatMode).toBe('all');
    });

    it('should have queue button', () => {
        const wrapper = mount(AudioPlayer);
        const queueButton = wrapper.find('[aria-label="Queue"]');
        expect(queueButton.exists()).toBe(true);
    });

    it('should have devices button', () => {
        const wrapper = mount(AudioPlayer);
        const devicesButton = wrapper.find('[aria-label="Devices"]');
        expect(devicesButton.exists()).toBe(true);
    });

    it('should show queue panel when queue button is clicked', async () => {
        const wrapper = mount(AudioPlayer);
        const queueButton = wrapper.find('[aria-label="Queue"]');

        await queueButton.trigger('click');

        expect(wrapper.text()).toContain('Queue');
    });

    it('should have disabled next button when no next track', () => {
        const wrapper = mount(AudioPlayer);
        const nextButton = wrapper.find('[aria-label="Next track"]');
        expect(nextButton.attributes('disabled')).toBeDefined();
    });

    it('should have disabled previous button when no previous track', () => {
        const wrapper = mount(AudioPlayer);
        const prevButton = wrapper.find('[aria-label="Previous track"]');
        expect(prevButton.attributes('disabled')).toBeDefined();
    });
});

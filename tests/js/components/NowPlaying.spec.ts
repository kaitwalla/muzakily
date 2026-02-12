import { describe, it, expect, beforeEach } from 'vitest';
import { mount } from '@vue/test-utils';
import { createPinia, setActivePinia } from 'pinia';
import NowPlaying from '@/components/player/NowPlaying.vue';
import { usePlayerStore } from '@/stores/player';
import { createMockSong } from '../utils/test-helpers';

describe('NowPlaying', () => {
    beforeEach(() => {
        setActivePinia(createPinia());
    });

    it('should render the component', () => {
        const wrapper = mount(NowPlaying);
        expect(wrapper.exists()).toBe(true);
    });

    it('should display "No song playing" when queue is empty', () => {
        const wrapper = mount(NowPlaying);
        expect(wrapper.text()).toContain('No song playing');
    });

    it('should display song title when playing', () => {
        const store = usePlayerStore();
        store.play([createMockSong({ title: 'Test Song Title' })], 0);

        const wrapper = mount(NowPlaying);
        expect(wrapper.text()).toContain('Test Song Title');
    });

    it('should display artist name when playing', () => {
        const store = usePlayerStore();
        store.play([createMockSong({ artist_name: 'Test Artist Name' })], 0);

        const wrapper = mount(NowPlaying);
        expect(wrapper.text()).toContain('Test Artist Name');
    });

    it('should display "Unknown Artist" when artist_name is null', () => {
        const store = usePlayerStore();
        store.play([createMockSong({ artist_name: null })], 0);

        const wrapper = mount(NowPlaying);
        expect(wrapper.text()).toContain('Unknown Artist');
    });

    it('should show album cover when available and showCover is true', () => {
        const store = usePlayerStore();
        store.play([createMockSong({ album_cover: 'https://example.com/cover.jpg' })], 0);

        const wrapper = mount(NowPlaying);
        const img = wrapper.find('img');
        expect(img.exists()).toBe(true);
        expect(img.attributes('src')).toBe('https://example.com/cover.jpg');
    });

    it('should show placeholder when no album cover', () => {
        const store = usePlayerStore();
        store.play([createMockSong({ album_cover: null })], 0);

        const wrapper = mount(NowPlaying);
        const img = wrapper.find('img');
        expect(img.exists()).toBe(false);
        // Should have placeholder SVG
        expect(wrapper.find('svg').exists()).toBe(true);
    });

    it('should hide cover when showCover is false', () => {
        const store = usePlayerStore();
        store.play([createMockSong({ album_cover: 'https://example.com/cover.jpg' })], 0);

        const wrapper = mount(NowPlaying, {
            props: { showCover: false },
        });
        const img = wrapper.find('img');
        expect(img.exists()).toBe(false);
    });

    describe('coverSize prop', () => {
        it('should apply small cover size', () => {
            const store = usePlayerStore();
            store.play([createMockSong()], 0);

            const wrapper = mount(NowPlaying, {
                props: { coverSize: 'sm' },
            });
            const coverContainer = wrapper.find('.w-10');
            expect(coverContainer.exists()).toBe(true);
        });

        it('should apply medium cover size by default', () => {
            const store = usePlayerStore();
            store.play([createMockSong()], 0);

            const wrapper = mount(NowPlaying);
            const coverContainer = wrapper.find('.w-12');
            expect(coverContainer.exists()).toBe(true);
        });

        it('should apply large cover size', () => {
            const store = usePlayerStore();
            store.play([createMockSong()], 0);

            const wrapper = mount(NowPlaying, {
                props: { coverSize: 'lg' },
            });
            const coverContainer = wrapper.find('.w-14');
            expect(coverContainer.exists()).toBe(true);
        });
    });

    it('should have proper alt text for album cover', () => {
        const store = usePlayerStore();
        store.play([createMockSong({
            album_cover: 'https://example.com/cover.jpg',
            album_name: 'My Album',
        })], 0);

        const wrapper = mount(NowPlaying);
        const img = wrapper.find('img');
        expect(img.attributes('alt')).toBe('My Album');
    });

    it('should use "Album" as fallback alt text', () => {
        const store = usePlayerStore();
        store.play([createMockSong({
            album_cover: 'https://example.com/cover.jpg',
            album_name: null,
        })], 0);

        const wrapper = mount(NowPlaying);
        const img = wrapper.find('img');
        expect(img.attributes('alt')).toBe('Album');
    });
});

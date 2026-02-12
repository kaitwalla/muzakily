import { describe, it, expect, beforeEach, vi } from 'vitest';
import { mount } from '@vue/test-utils';
import { createPinia, setActivePinia } from 'pinia';
import PlayPauseButton from '@/components/player/PlayPauseButton.vue';
import { usePlayerStore } from '@/stores/player';
import { createMockSong } from '../utils/test-helpers';

describe('PlayPauseButton', () => {
    beforeEach(() => {
        setActivePinia(createPinia());
    });

    it('should render the button', () => {
        const wrapper = mount(PlayPauseButton);
        expect(wrapper.find('button').exists()).toBe(true);
    });

    it('should show play icon when not playing', () => {
        const wrapper = mount(PlayPauseButton);
        expect(wrapper.find('[aria-label="Play"]').exists()).toBe(true);
    });

    it('should show pause icon when playing', () => {
        const store = usePlayerStore();
        store.play([createMockSong()], 0);
        store.isPlaying = true;

        const wrapper = mount(PlayPauseButton);
        expect(wrapper.find('[aria-label="Pause"]').exists()).toBe(true);
    });

    it('should be disabled when no song is playing', () => {
        const wrapper = mount(PlayPauseButton);
        expect(wrapper.find('button').attributes('disabled')).toBeDefined();
    });

    it('should be enabled when song is in queue', () => {
        const store = usePlayerStore();
        store.play([createMockSong()], 0);

        const wrapper = mount(PlayPauseButton);
        expect(wrapper.find('button').attributes('disabled')).toBeUndefined();
    });

    it('should be disabled when disabled prop is true', () => {
        const store = usePlayerStore();
        store.play([createMockSong()], 0);

        const wrapper = mount(PlayPauseButton, {
            props: { disabled: true },
        });
        expect(wrapper.find('button').attributes('disabled')).toBeDefined();
    });

    it('should call togglePlayPause when clicked', async () => {
        const store = usePlayerStore();
        store.play([createMockSong()], 0);
        const toggleSpy = vi.spyOn(store, 'togglePlayPause');

        const wrapper = mount(PlayPauseButton);
        await wrapper.find('button').trigger('click');

        expect(toggleSpy).toHaveBeenCalled();
    });

    describe('size prop', () => {
        it('should apply small size classes', () => {
            const store = usePlayerStore();
            store.play([createMockSong()], 0);

            const wrapper = mount(PlayPauseButton, {
                props: { size: 'sm' },
            });
            expect(wrapper.find('button').classes()).toContain('w-8');
            expect(wrapper.find('button').classes()).toContain('h-8');
        });

        it('should apply medium size classes by default', () => {
            const store = usePlayerStore();
            store.play([createMockSong()], 0);

            const wrapper = mount(PlayPauseButton);
            expect(wrapper.find('button').classes()).toContain('w-10');
            expect(wrapper.find('button').classes()).toContain('h-10');
        });

        it('should apply large size classes', () => {
            const store = usePlayerStore();
            store.play([createMockSong()], 0);

            const wrapper = mount(PlayPauseButton, {
                props: { size: 'lg' },
            });
            expect(wrapper.find('button').classes()).toContain('w-14');
            expect(wrapper.find('button').classes()).toContain('h-14');
        });
    });

    describe('variant prop', () => {
        it('should apply default variant classes', () => {
            const store = usePlayerStore();
            store.play([createMockSong()], 0);

            const wrapper = mount(PlayPauseButton);
            expect(wrapper.find('button').classes()).toContain('bg-gray-700');
        });

        it('should apply primary variant classes', () => {
            const store = usePlayerStore();
            store.play([createMockSong()], 0);

            const wrapper = mount(PlayPauseButton, {
                props: { variant: 'primary' },
            });
            expect(wrapper.find('button').classes()).toContain('bg-white');
        });
    });
});

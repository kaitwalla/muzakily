import { describe, it, expect, beforeEach, vi } from 'vitest';
import { mount } from '@vue/test-utils';
import { createPinia, setActivePinia } from 'pinia';
import ProgressBar from '@/components/player/ProgressBar.vue';
import { usePlayerStore } from '@/stores/player';

describe('ProgressBar', () => {
    beforeEach(() => {
        setActivePinia(createPinia());
    });

    it('should render the progress bar', () => {
        const wrapper = mount(ProgressBar);
        expect(wrapper.find('[role="slider"]').exists()).toBe(true);
    });

    it('should display current time', () => {
        const store = usePlayerStore();
        store.currentTime = 65; // 1:05

        const wrapper = mount(ProgressBar);
        expect(wrapper.text()).toContain('1:05');
    });

    it('should display duration by default', () => {
        const store = usePlayerStore();
        store.duration = 180; // 3:00

        const wrapper = mount(ProgressBar);
        expect(wrapper.text()).toContain('3:00');
    });

    it('should display time remaining when showTimeRemaining is true', () => {
        const store = usePlayerStore();
        store.currentTime = 60;
        store.duration = 180; // Remaining: 2:00

        const wrapper = mount(ProgressBar, {
            props: { showTimeRemaining: true },
        });
        expect(wrapper.text()).toContain('-2:00');
    });

    it('should hide time when showTime is false', () => {
        const store = usePlayerStore();
        store.currentTime = 60;
        store.duration = 180;

        const wrapper = mount(ProgressBar, {
            props: { showTime: false },
        });
        expect(wrapper.text()).not.toContain('1:00');
        expect(wrapper.text()).not.toContain('3:00');
    });

    it('should have correct ARIA attributes', () => {
        const store = usePlayerStore();
        store.currentTime = 60;
        store.duration = 180;

        const wrapper = mount(ProgressBar);
        const slider = wrapper.find('[role="slider"]');

        expect(slider.attributes('aria-valuenow')).toBe('60');
        expect(slider.attributes('aria-valuemin')).toBe('0');
        expect(slider.attributes('aria-valuemax')).toBe('180');
        expect(slider.attributes('aria-label')).toBe('Seek');
    });

    it('should be focusable via keyboard', () => {
        const wrapper = mount(ProgressBar);
        const slider = wrapper.find('[role="slider"]');

        expect(slider.attributes('tabindex')).toBe('0');
    });

    it('should seek on click', async () => {
        const store = usePlayerStore();
        store.duration = 100;
        const seekSpy = vi.spyOn(store, 'seek');

        const wrapper = mount(ProgressBar);
        const slider = wrapper.find('[role="slider"]');

        // Mock getBoundingClientRect
        const mockRect = { left: 0, width: 100 };
        vi.spyOn(slider.element, 'getBoundingClientRect').mockReturnValue(mockRect as DOMRect);

        // Click at 50%
        await slider.trigger('click', { clientX: 50 });

        expect(seekSpy).toHaveBeenCalledWith(50);
    });

    it('should handle keyboard navigation', async () => {
        const store = usePlayerStore();
        store.currentTime = 50;
        store.duration = 100;
        const seekSpy = vi.spyOn(store, 'seek');

        const wrapper = mount(ProgressBar);
        const slider = wrapper.find('[role="slider"]');

        // ArrowRight should seek forward
        await slider.trigger('keydown', { key: 'ArrowRight' });
        expect(seekSpy).toHaveBeenCalledWith(55); // +5 seconds

        // ArrowLeft should seek backward
        await slider.trigger('keydown', { key: 'ArrowLeft' });
        expect(seekSpy).toHaveBeenCalledWith(45); // -5 seconds

        // Home should go to start
        await slider.trigger('keydown', { key: 'Home' });
        expect(seekSpy).toHaveBeenCalledWith(0);

        // End should go to end
        await slider.trigger('keydown', { key: 'End' });
        expect(seekSpy).toHaveBeenCalledWith(100);
    });

    it('should display correct progress width', () => {
        const store = usePlayerStore();
        store.currentTime = 30;
        store.duration = 100;

        const wrapper = mount(ProgressBar);
        const progressFill = wrapper.find('[role="slider"] > div');

        expect(progressFill.attributes('style')).toContain('width: 30%');
    });
});

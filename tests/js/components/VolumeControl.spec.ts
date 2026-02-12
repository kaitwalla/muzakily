import { describe, it, expect, beforeEach, vi } from 'vitest';
import { mount } from '@vue/test-utils';
import { createPinia, setActivePinia } from 'pinia';
import VolumeControl from '@/components/player/VolumeControl.vue';
import { usePlayerStore } from '@/stores/player';

describe('VolumeControl', () => {
    beforeEach(() => {
        setActivePinia(createPinia());
    });

    it('should render the volume control', () => {
        const wrapper = mount(VolumeControl);
        expect(wrapper.find('button').exists()).toBe(true);
        expect(wrapper.find('input[type="range"]').exists()).toBe(true);
    });

    it('should show high volume icon when volume is >= 0.5', () => {
        const store = usePlayerStore();
        store.volume = 0.8;

        const wrapper = mount(VolumeControl);
        // All non-muted states have "Mute" label; we verify via the SVG path content
        const button = wrapper.find('[aria-label="Mute"]');
        expect(button.exists()).toBe(true);
        // High volume icon has the outer sound wave path
        expect(button.html()).toContain('M14 3.23v2.06');
    });

    it('should show low volume icon when volume is < 0.5', () => {
        const store = usePlayerStore();
        store.volume = 0.3;

        const wrapper = mount(VolumeControl);
        const button = wrapper.find('[aria-label="Mute"]');
        expect(button.exists()).toBe(true);
        // Low volume icon has smaller path without outer wave
        expect(button.html()).toContain('M18.5 12c0-1.77');
        expect(button.html()).not.toContain('M14 3.23v2.06');
    });

    it('should show muted icon when muted', () => {
        const store = usePlayerStore();
        store.isMuted = true;

        const wrapper = mount(VolumeControl);
        expect(wrapper.find('[aria-label="Unmute"]').exists()).toBe(true);
    });

    it('should show muted icon when volume is 0', () => {
        const store = usePlayerStore();
        store.volume = 0;

        const wrapper = mount(VolumeControl);
        expect(wrapper.find('[aria-label="Mute"]').exists()).toBe(true);
    });

    it('should toggle mute when button is clicked', async () => {
        const store = usePlayerStore();
        const toggleMuteSpy = vi.spyOn(store, 'toggleMute');

        const wrapper = mount(VolumeControl);
        await wrapper.find('button').trigger('click');

        expect(toggleMuteSpy).toHaveBeenCalled();
    });

    it('should set volume when slider changes', async () => {
        const store = usePlayerStore();
        const setVolumeSpy = vi.spyOn(store, 'setVolume');

        const wrapper = mount(VolumeControl);
        const input = wrapper.find('input[type="range"]');

        await input.setValue('0.5');

        expect(setVolumeSpy).toHaveBeenCalledWith(0.5);
    });

    it('should have correct slider attributes', () => {
        const wrapper = mount(VolumeControl);
        const input = wrapper.find('input[type="range"]');

        expect(input.attributes('min')).toBe('0');
        expect(input.attributes('max')).toBe('1');
        expect(input.attributes('step')).toBe('0.01');
        expect(input.attributes('aria-label')).toBe('Volume');
    });

    it('should reflect current volume in slider', () => {
        const store = usePlayerStore();
        store.volume = 0.75;

        const wrapper = mount(VolumeControl);
        const input = wrapper.find('input[type="range"]');

        expect((input.element as HTMLInputElement).value).toBe('0.75');
    });

    it('should have aria-pressed on mute button', () => {
        const store = usePlayerStore();
        store.isMuted = true;

        const wrapper = mount(VolumeControl);
        expect(wrapper.find('button').attributes('aria-pressed')).toBe('true');
    });
});

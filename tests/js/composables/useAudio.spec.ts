import { describe, it, expect, beforeEach, vi } from 'vitest';
import { mount } from '@vue/test-utils';
import { defineComponent, nextTick } from 'vue';
import { useAudio } from '@/composables/useAudio';
import { usePlayerStore } from '@/stores/player';

// Create a test component that uses the composable
const createTestComponent = (options = {}) => defineComponent({
    template: '<audio ref="audioRef" />',
    setup() {
        return useAudio(options);
    },
});

describe('useAudio', () => {
    beforeEach(() => {
        const store = usePlayerStore();
        store.clearQueue();
        vi.clearAllMocks();
    });

    describe('initial state', () => {
        it('should have audioRef defined after mount', () => {
            const wrapper = mount(createTestComponent({ autoRegister: false }));
            const vm = wrapper.vm as ReturnType<typeof useAudio>;

            // audioRef will be set by Vue after mount
            expect(vm.audioRef).toBeDefined();
        });

        it('should not be ready initially when autoRegister is false', () => {
            const wrapper = mount(createTestComponent({ autoRegister: false }));
            const vm = wrapper.vm as ReturnType<typeof useAudio>;

            expect(vm.isReady).toBe(false);
        });

        it('should have no error initially', () => {
            const wrapper = mount(createTestComponent({ autoRegister: false }));
            const vm = wrapper.vm as ReturnType<typeof useAudio>;

            expect(vm.error).toBeNull();
        });
    });

    describe('registerAudioElement', () => {
        it('should register audio element with store', async () => {
            const store = usePlayerStore();
            const setAudioElementSpy = vi.spyOn(store, 'setAudioElement');

            const wrapper = mount(createTestComponent({ autoRegister: false }));
            const vm = wrapper.vm as ReturnType<typeof useAudio>;

            await nextTick();
            vm.registerAudioElement();

            expect(setAudioElementSpy).toHaveBeenCalled();
            expect(vm.isReady).toBe(true);
        });

        it('should auto-register when autoRegister is true', async () => {
            const store = usePlayerStore();
            const setAudioElementSpy = vi.spyOn(store, 'setAudioElement');

            mount(createTestComponent({ autoRegister: true }));
            await nextTick();

            expect(setAudioElementSpy).toHaveBeenCalled();
        });
    });

    describe('unregisterAudioElement', () => {
        it('should set isReady to false', async () => {
            const wrapper = mount(createTestComponent({ autoRegister: false }));
            const vm = wrapper.vm as ReturnType<typeof useAudio>;

            await nextTick();
            vm.registerAudioElement();
            expect(vm.isReady).toBe(true);

            vm.unregisterAudioElement();
            expect(vm.isReady).toBe(false);
        });
    });

    describe('media session handlers', () => {
        it('should set up media session handlers on register', async () => {
            const wrapper = mount(createTestComponent({ autoRegister: false }));
            const vm = wrapper.vm as ReturnType<typeof useAudio>;

            await nextTick();
            vm.registerAudioElement();

            // Check that setActionHandler was called
            expect(navigator.mediaSession.setActionHandler).toHaveBeenCalledWith('play', expect.any(Function));
            expect(navigator.mediaSession.setActionHandler).toHaveBeenCalledWith('pause', expect.any(Function));
            expect(navigator.mediaSession.setActionHandler).toHaveBeenCalledWith('previoustrack', expect.any(Function));
            expect(navigator.mediaSession.setActionHandler).toHaveBeenCalledWith('nexttrack', expect.any(Function));
            expect(navigator.mediaSession.setActionHandler).toHaveBeenCalledWith('seekto', expect.any(Function));
        });
    });
});

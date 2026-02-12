import { describe, it, expect, vi } from 'vitest';
import { mount } from '@vue/test-utils';
import UploadDropZone from '@/components/upload/UploadDropZone.vue';

describe('UploadDropZone', () => {
    it('should render the drop zone', () => {
        const wrapper = mount(UploadDropZone);
        expect(wrapper.find('.border-dashed').exists()).toBe(true);
    });

    it('should display instructions text', () => {
        const wrapper = mount(UploadDropZone);
        expect(wrapper.text()).toContain('Drag and drop music files here');
        expect(wrapper.text()).toContain('or click to browse');
    });

    it('should display supported formats', () => {
        const wrapper = mount(UploadDropZone);
        expect(wrapper.text()).toContain('Supports MP3, M4A, and FLAC files up to 100MB');
    });

    it('should have hidden file input', () => {
        const wrapper = mount(UploadDropZone);
        const input = wrapper.find('input[type="file"]');
        expect(input.exists()).toBe(true);
        expect(input.classes()).toContain('hidden');
    });

    it('should accept audio file types', () => {
        const wrapper = mount(UploadDropZone);
        const input = wrapper.find('input[type="file"]');
        expect(input.attributes('accept')).toContain('.mp3');
        expect(input.attributes('accept')).toContain('.m4a');
        expect(input.attributes('accept')).toContain('.flac');
    });

    it('should allow multiple file selection', () => {
        const wrapper = mount(UploadDropZone);
        const input = wrapper.find('input[type="file"]');
        expect(input.attributes('multiple')).toBeDefined();
    });

    it('should show drag state when dragging over', async () => {
        const wrapper = mount(UploadDropZone);

        await wrapper.find('.border-dashed').trigger('dragover');

        expect(wrapper.find('.border-dashed').classes()).toContain('border-green-500');
        expect(wrapper.text()).toContain('Drop files here');
    });

    it('should reset drag state on drag leave', async () => {
        const wrapper = mount(UploadDropZone);

        await wrapper.find('.border-dashed').trigger('dragover');
        await wrapper.find('.border-dashed').trigger('dragleave');

        expect(wrapper.find('.border-dashed').classes()).not.toContain('border-green-500');
        expect(wrapper.text()).toContain('Drag and drop music files here');
    });

    it('should emit files-dropped on drop', async () => {
        const wrapper = mount(UploadDropZone);
        const mockFiles = {
            length: 2,
            0: new File([''], 'song1.mp3', { type: 'audio/mpeg' }),
            1: new File([''], 'song2.mp3', { type: 'audio/mpeg' }),
        } as FileList;

        await wrapper.find('.border-dashed').trigger('drop', {
            dataTransfer: { files: mockFiles },
        });

        expect(wrapper.emitted('files-dropped')).toHaveLength(1);
        expect(wrapper.emitted('files-dropped')![0]).toEqual([mockFiles]);
    });

    it('should reset drag state after drop', async () => {
        const wrapper = mount(UploadDropZone);
        const mockFiles = {
            length: 1,
            0: new File([''], 'song.mp3', { type: 'audio/mpeg' }),
        } as FileList;

        await wrapper.find('.border-dashed').trigger('dragover');
        expect(wrapper.find('.border-dashed').classes()).toContain('border-green-500');

        await wrapper.find('.border-dashed').trigger('drop', {
            dataTransfer: { files: mockFiles },
        });

        expect(wrapper.find('.border-dashed').classes()).not.toContain('border-green-500');
    });

    it('should trigger file input click on zone click', async () => {
        const wrapper = mount(UploadDropZone);
        const input = wrapper.find('input[type="file"]');
        const clickSpy = vi.spyOn(input.element, 'click');

        await wrapper.find('.border-dashed').trigger('click');

        expect(clickSpy).toHaveBeenCalled();
    });

    it('should emit files-dropped when files are selected via input', async () => {
        const wrapper = mount(UploadDropZone);
        const input = wrapper.find('input[type="file"]');

        const mockFile = new File([''], 'song.mp3', { type: 'audio/mpeg' });
        const mockFiles = {
            length: 1,
            0: mockFile,
        } as FileList;

        // Simulate file selection
        Object.defineProperty(input.element, 'files', { value: mockFiles });
        await input.trigger('change');

        expect(wrapper.emitted('files-dropped')).toHaveLength(1);
        expect(wrapper.emitted('files-dropped')![0]).toEqual([mockFiles]);
    });

    it('should not emit when drop has no files', async () => {
        const wrapper = mount(UploadDropZone);

        await wrapper.find('.border-dashed').trigger('drop', {
            dataTransfer: { files: { length: 0 } },
        });

        expect(wrapper.emitted('files-dropped')).toBeUndefined();
    });

    it('should be cursor-pointer', () => {
        const wrapper = mount(UploadDropZone);
        expect(wrapper.find('.border-dashed').classes()).toContain('cursor-pointer');
    });
});

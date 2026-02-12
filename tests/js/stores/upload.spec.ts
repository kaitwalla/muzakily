import { describe, it, expect, beforeEach, vi } from 'vitest';
import { createPinia, setActivePinia } from 'pinia';
import { useUploadStore } from '@/stores/upload';
import * as uploadApi from '@/api/upload';

vi.mock('@/api/upload');

const createMockFile = (name: string, size: number = 1024 * 1024, type: string = 'audio/mpeg'): File => {
    const file = new File([''], name, { type });
    Object.defineProperty(file, 'size', { value: size });
    return file;
};

describe('useUploadStore', () => {
    beforeEach(() => {
        setActivePinia(createPinia());
        vi.clearAllMocks();
        vi.mocked(uploadApi.isValidAudioFile).mockReturnValue(true);
        vi.mocked(uploadApi.getMaxFileSize).mockReturnValue(100 * 1024 * 1024); // 100MB
    });

    describe('initial state', () => {
        it('should have empty queue', () => {
            const store = useUploadStore();
            expect(store.queue).toEqual([]);
        });

        it('should not be processing', () => {
            const store = useUploadStore();
            expect(store.isProcessing).toBe(false);
        });

        it('should have zero counts', () => {
            const store = useUploadStore();
            expect(store.pendingCount).toBe(0);
            expect(store.uploadingCount).toBe(0);
            expect(store.completedCount).toBe(0);
            expect(store.errorCount).toBe(0);
        });

        it('should not have items', () => {
            const store = useUploadStore();
            expect(store.hasItems).toBe(false);
        });

        it('should not have active uploads', () => {
            const store = useUploadStore();
            expect(store.hasActiveUploads).toBe(false);
        });
    });

    describe('computed properties', () => {
        it('should count pending items', () => {
            const store = useUploadStore();
            store.queue = [
                { id: '1', file: createMockFile('song1.mp3'), status: 'pending', progress: 0 },
                { id: '2', file: createMockFile('song2.mp3'), status: 'pending', progress: 0 },
                { id: '3', file: createMockFile('song3.mp3'), status: 'completed', progress: 100 },
            ];
            expect(store.pendingCount).toBe(2);
        });

        it('should count uploading items', () => {
            const store = useUploadStore();
            store.queue = [
                { id: '1', file: createMockFile('song1.mp3'), status: 'uploading', progress: 50 },
                { id: '2', file: createMockFile('song2.mp3'), status: 'pending', progress: 0 },
            ];
            expect(store.uploadingCount).toBe(1);
        });

        it('should count completed items', () => {
            const store = useUploadStore();
            store.queue = [
                { id: '1', file: createMockFile('song1.mp3'), status: 'completed', progress: 100 },
                { id: '2', file: createMockFile('song2.mp3'), status: 'completed', progress: 100 },
            ];
            expect(store.completedCount).toBe(2);
        });

        it('should count error items', () => {
            const store = useUploadStore();
            store.queue = [
                { id: '1', file: createMockFile('song1.mp3'), status: 'error', progress: 0, error: 'Failed' },
            ];
            expect(store.errorCount).toBe(1);
        });

        it('should detect active uploads', () => {
            const store = useUploadStore();
            store.queue = [
                { id: '1', file: createMockFile('song1.mp3'), status: 'uploading', progress: 50 },
            ];
            expect(store.hasActiveUploads).toBe(true);
        });

        it('should detect processing as active', () => {
            const store = useUploadStore();
            store.queue = [
                { id: '1', file: createMockFile('song1.mp3'), status: 'processing', progress: 100 },
            ];
            expect(store.hasActiveUploads).toBe(true);
        });
    });

    describe('addFiles', () => {
        it('should add valid files to queue', () => {
            const store = useUploadStore();
            const files = [createMockFile('song1.mp3'), createMockFile('song2.mp3')];

            // Prevent auto-processing
            store.isProcessing = true;
            const result = store.addFiles(files);

            expect(result.added).toBe(2);
            expect(result.rejected).toEqual([]);
            expect(store.queue).toHaveLength(2);
            expect(store.queue[0].status).toBe('pending');
        });

        it('should reject invalid audio files', () => {
            vi.mocked(uploadApi.isValidAudioFile).mockReturnValue(false);

            const store = useUploadStore();
            const files = [createMockFile('document.pdf')];

            store.isProcessing = true;
            const result = store.addFiles(files);

            expect(result.added).toBe(0);
            expect(result.rejected).toContain('document.pdf: Unsupported format');
            expect(store.queue).toHaveLength(0);
        });

        it('should reject files that are too large', () => {
            vi.mocked(uploadApi.getMaxFileSize).mockReturnValue(100 * 1024 * 1024); // 100MB
            const largeFile = createMockFile('large.mp3', 150 * 1024 * 1024); // 150MB

            const store = useUploadStore();
            store.isProcessing = true;
            const result = store.addFiles([largeFile]);

            expect(result.added).toBe(0);
            expect(result.rejected).toContain('large.mp3: File too large (max 100MB)');
        });

        it('should generate unique IDs for each file', () => {
            const store = useUploadStore();
            const files = [createMockFile('song1.mp3'), createMockFile('song2.mp3')];

            store.isProcessing = true;
            store.addFiles(files);

            expect(store.queue[0].id).not.toBe(store.queue[1].id);
        });
    });

    describe('removeItem', () => {
        it('should remove pending item from queue', () => {
            const store = useUploadStore();
            store.queue = [
                { id: '1', file: createMockFile('song1.mp3'), status: 'pending', progress: 0 },
                { id: '2', file: createMockFile('song2.mp3'), status: 'pending', progress: 0 },
            ];

            store.removeItem('1');

            expect(store.queue).toHaveLength(1);
            expect(store.queue[0].id).toBe('2');
        });

        it('should not remove currently uploading item', () => {
            const store = useUploadStore();
            store.queue = [
                { id: '1', file: createMockFile('song1.mp3'), status: 'uploading', progress: 50 },
            ];

            store.removeItem('1');

            expect(store.queue).toHaveLength(1);
        });

        it('should remove completed item', () => {
            const store = useUploadStore();
            store.queue = [
                { id: '1', file: createMockFile('song1.mp3'), status: 'completed', progress: 100 },
            ];

            store.removeItem('1');

            expect(store.queue).toHaveLength(0);
        });
    });

    describe('clearCompleted', () => {
        it('should clear completed and error items', () => {
            const store = useUploadStore();
            store.queue = [
                { id: '1', file: createMockFile('song1.mp3'), status: 'completed', progress: 100 },
                { id: '2', file: createMockFile('song2.mp3'), status: 'error', progress: 0, error: 'Failed' },
                { id: '3', file: createMockFile('song3.mp3'), status: 'pending', progress: 0 },
            ];

            store.clearCompleted();

            expect(store.queue).toHaveLength(1);
            expect(store.queue[0].id).toBe('3');
        });
    });

    describe('clearAll', () => {
        it('should clear all non-uploading items', () => {
            const store = useUploadStore();
            store.queue = [
                { id: '1', file: createMockFile('song1.mp3'), status: 'uploading', progress: 50 },
                { id: '2', file: createMockFile('song2.mp3'), status: 'pending', progress: 0 },
                { id: '3', file: createMockFile('song3.mp3'), status: 'completed', progress: 100 },
            ];

            store.clearAll();

            expect(store.queue).toHaveLength(1);
            expect(store.queue[0].id).toBe('1');
        });
    });

    describe('retryFailed', () => {
        it('should reset failed items to pending', () => {
            const store = useUploadStore();
            store.isProcessing = true; // Prevent auto-processing
            store.queue = [
                { id: '1', file: createMockFile('song1.mp3'), status: 'error', progress: 25, error: 'Network error' },
                { id: '2', file: createMockFile('song2.mp3'), status: 'completed', progress: 100 },
            ];

            store.retryFailed();

            expect(store.queue[0].status).toBe('pending');
            expect(store.queue[0].progress).toBe(0);
            expect(store.queue[0].error).toBeUndefined();
            expect(store.queue[1].status).toBe('completed');
        });
    });
});

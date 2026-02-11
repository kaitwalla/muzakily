import { defineStore } from 'pinia';
import { ref, computed } from 'vue';
import { uploadSong, isValidAudioFile, getMaxFileSize } from '@/api/upload';

export type UploadStatus = 'pending' | 'uploading' | 'processing' | 'completed' | 'error';

export interface UploadItem {
    id: string;
    file: File;
    status: UploadStatus;
    progress: number;
    error?: string;
    jobId?: string;
}

export const useUploadStore = defineStore('upload', () => {
    const queue = ref<UploadItem[]>([]);
    const isProcessing = ref(false);

    const pendingCount = computed(() =>
        queue.value.filter(item => item.status === 'pending').length
    );

    const uploadingCount = computed(() =>
        queue.value.filter(item => item.status === 'uploading').length
    );

    const completedCount = computed(() =>
        queue.value.filter(item => item.status === 'completed').length
    );

    const errorCount = computed(() =>
        queue.value.filter(item => item.status === 'error').length
    );

    const hasItems = computed(() => queue.value.length > 0);

    const hasActiveUploads = computed(() =>
        queue.value.some(item =>
            item.status === 'pending' ||
            item.status === 'uploading' ||
            item.status === 'processing'
        )
    );

    function generateId(): string {
        return `upload-${Date.now()}-${Math.random().toString(36).substr(2, 9)}`;
    }

    function addFiles(files: FileList | File[]): { added: number; rejected: string[] } {
        const rejected: string[] = [];
        let added = 0;
        const maxSize = getMaxFileSize();

        for (const file of files) {
            if (!isValidAudioFile(file)) {
                rejected.push(`${file.name}: Unsupported format`);
                continue;
            }

            if (file.size > maxSize) {
                rejected.push(`${file.name}: File too large (max 100MB)`);
                continue;
            }

            queue.value.push({
                id: generateId(),
                file,
                status: 'pending',
                progress: 0,
            });
            added++;
        }

        // Auto-start processing if not already running
        if (added > 0 && !isProcessing.value) {
            processQueue();
        }

        return { added, rejected };
    }

    function removeItem(id: string): void {
        const index = queue.value.findIndex(item => item.id === id);
        if (index !== -1) {
            const item = queue.value[index];
            // Only remove if not currently uploading
            if (item.status !== 'uploading') {
                queue.value.splice(index, 1);
            }
        }
    }

    function clearCompleted(): void {
        queue.value = queue.value.filter(
            item => item.status !== 'completed' && item.status !== 'error'
        );
    }

    function clearAll(): void {
        // Only clear items that aren't currently uploading
        queue.value = queue.value.filter(item => item.status === 'uploading');
    }

    function retryFailed(): void {
        queue.value.forEach(item => {
            if (item.status === 'error') {
                item.status = 'pending';
                item.progress = 0;
                item.error = undefined;
            }
        });

        if (!isProcessing.value) {
            processQueue();
        }
    }

    async function processQueue(): Promise<void> {
        if (isProcessing.value) return;

        isProcessing.value = true;

        while (true) {
            const nextItem = queue.value.find(item => item.status === 'pending');
            if (!nextItem) break;

            await processItem(nextItem);
        }

        isProcessing.value = false;
    }

    async function processItem(item: UploadItem): Promise<void> {
        item.status = 'uploading';
        item.progress = 0;

        try {
            const response = await uploadSong(item.file, (percent) => {
                item.progress = percent;
            });

            item.status = 'processing';
            item.progress = 100;
            item.jobId = response.job_id;

            // Mark as completed after a brief delay
            // In a real app, you might poll for job status here
            setTimeout(() => {
                item.status = 'completed';
            }, 1000);

        } catch (error: unknown) {
            item.status = 'error';
            if (error instanceof Error) {
                item.error = error.message;
            } else {
                item.error = 'Upload failed';
            }
        }
    }

    return {
        queue,
        isProcessing,
        pendingCount,
        uploadingCount,
        completedCount,
        errorCount,
        hasItems,
        hasActiveUploads,
        addFiles,
        removeItem,
        clearCompleted,
        clearAll,
        retryFailed,
    };
});

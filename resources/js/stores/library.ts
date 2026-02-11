import { defineStore } from 'pinia';
import { ref, computed } from 'vue';
import { apiClient } from '@/api/client';

export interface ScanProgress {
    total_files: number;
    scanned_files: number;
    new_songs: number;
    updated_songs: number;
    errors: number;
}

export interface ScanStatus {
    status: 'idle' | 'started' | 'in_progress' | 'completed' | 'failed';
    progress?: ScanProgress;
    started_at?: string;
    completed_at?: string;
    error?: string;
}

export const useLibraryStore = defineStore('library', () => {
    const scanStatus = ref<ScanStatus>({ status: 'idle' });
    const pollInterval = ref<ReturnType<typeof setInterval> | null>(null);

    const isScanning = computed(() =>
        scanStatus.value.status === 'started' || scanStatus.value.status === 'in_progress'
    );

    async function fetchStatus(): Promise<void> {
        try {
            const response = await apiClient.get<{ data: ScanStatus }>('/admin/library/scan/status');
            scanStatus.value = response.data.data;
        } catch {
            scanStatus.value = { status: 'idle' };
        }
    }

    function startPolling(): void {
        if (pollInterval.value) return;

        pollInterval.value = setInterval(async () => {
            await fetchStatus();
            if (!isScanning.value && pollInterval.value) {
                stopPolling();
            }
        }, 2000);
    }

    function stopPolling(): void {
        if (pollInterval.value) {
            clearInterval(pollInterval.value);
            pollInterval.value = null;
        }
    }

    async function triggerScan(force = false): Promise<void> {
        try {
            scanStatus.value = { status: 'started' };
            await apiClient.post('/admin/library/scan', { force });
            startPolling();
        } catch (error) {
            scanStatus.value = { status: 'failed', error: 'Failed to start scan' };
            throw error;
        }
    }

    return {
        scanStatus,
        isScanning,
        fetchStatus,
        triggerScan,
        startPolling,
        stopPolling,
    };
});

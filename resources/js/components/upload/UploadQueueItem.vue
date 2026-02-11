<script setup lang="ts">
import { computed } from 'vue';
import type { UploadItem } from '@/stores/upload';
import { formatFileSize } from '@/api/upload';

const props = defineProps<{
    item: UploadItem;
}>();

const emit = defineEmits<{
    (e: 'remove', id: string): void;
}>();

const statusText = computed(() => {
    switch (props.item.status) {
        case 'pending':
            return 'Waiting...';
        case 'uploading':
            return `Uploading ${props.item.progress}%`;
        case 'processing':
            return 'Processing...';
        case 'completed':
            return 'Completed';
        case 'error':
            return props.item.error || 'Error';
        default:
            return '';
    }
});

const statusColor = computed(() => {
    switch (props.item.status) {
        case 'completed':
            return 'text-green-500';
        case 'error':
            return 'text-red-500';
        case 'uploading':
        case 'processing':
            return 'text-blue-500';
        default:
            return 'text-gray-400';
    }
});

const canRemove = computed(() =>
    props.item.status !== 'uploading' && props.item.status !== 'processing'
);

function handleRemove(): void {
    if (canRemove.value) {
        emit('remove', props.item.id);
    }
}
</script>

<template>
    <div class="bg-gray-800 rounded-lg p-4">
        <div class="flex items-center gap-4">
            <!-- Icon -->
            <div class="flex-shrink-0">
                <svg
                    v-if="item.status === 'completed'"
                    class="w-8 h-8 text-green-500"
                    fill="none"
                    stroke="currentColor"
                    viewBox="0 0 24 24"
                >
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7" />
                </svg>
                <svg
                    v-else-if="item.status === 'error'"
                    class="w-8 h-8 text-red-500"
                    fill="none"
                    stroke="currentColor"
                    viewBox="0 0 24 24"
                >
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" />
                </svg>
                <svg
                    v-else
                    class="w-8 h-8 text-gray-500"
                    fill="none"
                    stroke="currentColor"
                    viewBox="0 0 24 24"
                >
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 19V6l12-3v13M9 19c0 1.105-1.343 2-3 2s-3-.895-3-2 1.343-2 3-2 3 .895 3 2zm12-3c0 1.105-1.343 2-3 2s-3-.895-3-2 1.343-2 3-2 3 .895 3 2zM9 10l12-3" />
                </svg>
            </div>

            <!-- File info -->
            <div class="flex-1 min-w-0">
                <p class="text-white font-medium truncate">{{ item.file.name }}</p>
                <div class="flex items-center gap-2 text-sm">
                    <span class="text-gray-500">{{ formatFileSize(item.file.size) }}</span>
                    <span class="text-gray-600">-</span>
                    <span :class="statusColor">{{ statusText }}</span>
                </div>
            </div>

            <!-- Remove button -->
            <button
                v-if="canRemove"
                @click="handleRemove"
                class="p-2 text-gray-400 hover:text-white hover:bg-gray-700 rounded-lg transition-colors"
                title="Remove"
            >
                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" />
                </svg>
            </button>

            <!-- Spinner for active states -->
            <div v-if="item.status === 'uploading' || item.status === 'processing'" class="flex-shrink-0">
                <svg class="w-6 h-6 text-blue-500 animate-spin" fill="none" viewBox="0 0 24 24">
                    <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4" />
                    <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z" />
                </svg>
            </div>
        </div>

        <!-- Progress bar -->
        <div
            v-if="item.status === 'uploading'"
            class="mt-3 h-1.5 bg-gray-700 rounded-full overflow-hidden"
        >
            <div
                class="h-full bg-blue-500 transition-all duration-300 ease-out"
                :style="{ width: `${item.progress}%` }"
            />
        </div>
    </div>
</template>

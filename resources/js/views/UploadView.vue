<script setup lang="ts">
import { ref } from 'vue';
import { useUploadStore } from '@/stores/upload';
import UploadDropZone from '@/components/upload/UploadDropZone.vue';
import UploadQueueItem from '@/components/upload/UploadQueueItem.vue';

const uploadStore = useUploadStore();
const rejectedFiles = ref<string[]>([]);

function handleFilesDropped(files: FileList): void {
    const result = uploadStore.addFiles(files);
    rejectedFiles.value = result.rejected;

    // Clear rejected message after 5 seconds
    if (result.rejected.length > 0) {
        setTimeout(() => {
            rejectedFiles.value = [];
        }, 5000);
    }
}

function dismissRejected(): void {
    rejectedFiles.value = [];
}
</script>

<template>
    <div class="max-w-3xl mx-auto">
        <div class="mb-6">
            <h1 class="text-3xl font-bold text-white">Upload Music</h1>
            <p class="text-gray-400 mt-2">
                Add songs to your library by uploading audio files.
            </p>
        </div>

        <!-- Rejected files alert -->
        <div
            v-if="rejectedFiles.length > 0"
            class="mb-6 bg-red-900/20 border border-red-800 rounded-lg p-4"
        >
            <div class="flex items-start gap-3">
                <svg class="w-5 h-5 text-red-500 mt-0.5 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4m0 4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z" />
                </svg>
                <div class="flex-1">
                    <p class="text-red-400 font-medium">Some files could not be added:</p>
                    <ul class="mt-1 text-sm text-red-300">
                        <li v-for="(msg, index) in rejectedFiles" :key="index">{{ msg }}</li>
                    </ul>
                </div>
                <button
                    @click="dismissRejected"
                    class="text-red-400 hover:text-red-300"
                >
                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" />
                    </svg>
                </button>
            </div>
        </div>

        <!-- Drop zone -->
        <UploadDropZone @files-dropped="handleFilesDropped" />

        <!-- Queue section -->
        <div v-if="uploadStore.hasItems" class="mt-8">
            <div class="flex items-center justify-between mb-4">
                <h2 class="text-xl font-semibold text-white">Upload Queue</h2>
                <div class="flex items-center gap-3">
                    <button
                        v-if="uploadStore.errorCount > 0"
                        @click="uploadStore.retryFailed"
                        class="px-3 py-1.5 text-sm bg-yellow-600 hover:bg-yellow-700 text-white rounded-lg transition-colors"
                    >
                        Retry Failed
                    </button>
                    <button
                        v-if="uploadStore.completedCount > 0 || uploadStore.errorCount > 0"
                        @click="uploadStore.clearCompleted"
                        class="px-3 py-1.5 text-sm bg-gray-700 hover:bg-gray-600 text-white rounded-lg transition-colors"
                    >
                        Clear Finished
                    </button>
                </div>
            </div>

            <!-- Stats -->
            <div class="flex gap-4 mb-4 text-sm">
                <span v-if="uploadStore.pendingCount > 0" class="text-gray-400">
                    {{ uploadStore.pendingCount }} pending
                </span>
                <span v-if="uploadStore.uploadingCount > 0" class="text-blue-400">
                    {{ uploadStore.uploadingCount }} uploading
                </span>
                <span v-if="uploadStore.completedCount > 0" class="text-green-400">
                    {{ uploadStore.completedCount }} completed
                </span>
                <span v-if="uploadStore.errorCount > 0" class="text-red-400">
                    {{ uploadStore.errorCount }} failed
                </span>
            </div>

            <!-- Queue items -->
            <div class="space-y-2">
                <UploadQueueItem
                    v-for="item in uploadStore.queue"
                    :key="item.id"
                    :item="item"
                    @remove="uploadStore.removeItem"
                />
            </div>
        </div>

        <!-- Empty state -->
        <div v-else class="mt-12 text-center text-gray-500">
            <p>No files in queue</p>
            <p class="text-sm mt-1">Drop some files above to get started</p>
        </div>

        <!-- Supported formats info -->
        <div class="mt-12 p-4 bg-gray-800/50 rounded-lg">
            <h3 class="text-white font-medium mb-2">Supported Formats</h3>
            <div class="grid grid-cols-3 gap-4 text-sm">
                <div>
                    <span class="text-gray-400">MP3</span>
                    <p class="text-gray-500">Most compatible format</p>
                </div>
                <div>
                    <span class="text-gray-400">M4A / AAC</span>
                    <p class="text-gray-500">Apple/iTunes format</p>
                </div>
                <div>
                    <span class="text-gray-400">FLAC</span>
                    <p class="text-gray-500">Lossless audio</p>
                </div>
            </div>
            <p class="text-gray-500 text-sm mt-3">
                Maximum file size: 100 MB per file
            </p>
        </div>
    </div>
</template>

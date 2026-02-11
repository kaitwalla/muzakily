<script setup lang="ts">
import { ref } from 'vue';

const emit = defineEmits<{
    (e: 'files-dropped', files: FileList): void;
}>();

const isDragging = ref(false);
const fileInput = ref<HTMLInputElement | null>(null);

function handleDragOver(event: DragEvent): void {
    event.preventDefault();
    isDragging.value = true;
}

function handleDragLeave(): void {
    isDragging.value = false;
}

function handleDrop(event: DragEvent): void {
    event.preventDefault();
    isDragging.value = false;

    if (event.dataTransfer?.files?.length) {
        emit('files-dropped', event.dataTransfer.files);
    }
}

function handleClick(): void {
    fileInput.value?.click();
}

function handleFileSelect(event: Event): void {
    const target = event.target as HTMLInputElement;
    if (target.files?.length) {
        emit('files-dropped', target.files);
        target.value = ''; // Reset for next selection
    }
}
</script>

<template>
    <div
        @dragover="handleDragOver"
        @dragleave="handleDragLeave"
        @drop="handleDrop"
        @click="handleClick"
        class="border-2 border-dashed rounded-lg p-12 text-center cursor-pointer transition-all"
        :class="[
            isDragging
                ? 'border-green-500 bg-green-500/10'
                : 'border-gray-600 hover:border-gray-500 hover:bg-gray-800/50'
        ]"
    >
        <input
            ref="fileInput"
            type="file"
            multiple
            accept=".mp3,.m4a,.flac,audio/mpeg,audio/mp4,audio/flac"
            class="hidden"
            @change="handleFileSelect"
        />

        <div class="flex flex-col items-center gap-4">
            <svg
                class="w-16 h-16"
                :class="isDragging ? 'text-green-500' : 'text-gray-500'"
                fill="none"
                stroke="currentColor"
                viewBox="0 0 24 24"
            >
                <path
                    stroke-linecap="round"
                    stroke-linejoin="round"
                    stroke-width="1.5"
                    d="M7 16a4 4 0 01-.88-7.903A5 5 0 1115.9 6L16 6a5 5 0 011 9.9M15 13l-3-3m0 0l-3 3m3-3v12"
                />
            </svg>

            <div>
                <p class="text-lg font-medium" :class="isDragging ? 'text-green-500' : 'text-white'">
                    {{ isDragging ? 'Drop files here' : 'Drag and drop music files here' }}
                </p>
                <p class="text-gray-400 mt-1">
                    or click to browse
                </p>
            </div>

            <div class="text-sm text-gray-500">
                Supports MP3, M4A, and FLAC files up to 100MB
            </div>
        </div>
    </div>
</template>

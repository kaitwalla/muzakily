<script setup lang="ts">
import { ref } from 'vue';
import { useQueue, usePlayer } from '@/composables';
import type { QueueItem } from '@/types/player';

const emit = defineEmits<{
    close: [];
}>();

const { queue, currentIndex, isEmpty, removeFromQueue, clearQueue, jumpToIndex } = useQueue();
const { isPlaying } = usePlayer();

const draggedItem = ref<string | null>(null);
const dragOverItem = ref<string | null>(null);

function handlePlayItem(_item: QueueItem, index: number): void {
    jumpToIndex(index);
}

function handleRemoveItem(item: QueueItem): void {
    removeFromQueue(item.id);
}

function handleClearQueue(): void {
    clearQueue();
}

function handleDragStart(event: DragEvent, item: QueueItem): void {
    draggedItem.value = item.id;
    if (event.dataTransfer) {
        event.dataTransfer.effectAllowed = 'move';
        event.dataTransfer.setData('text/plain', item.id);
    }
}

function handleDragOver(event: DragEvent, item: QueueItem): void {
    event.preventDefault();
    if (event.dataTransfer) {
        event.dataTransfer.dropEffect = 'move';
    }
    dragOverItem.value = item.id;
}

function handleDragLeave(): void {
    dragOverItem.value = null;
}

function handleDrop(event: DragEvent): void {
    event.preventDefault();
    draggedItem.value = null;
    dragOverItem.value = null;
    // Note: Actual reordering would require store support
}

function handleDragEnd(): void {
    draggedItem.value = null;
    dragOverItem.value = null;
}

function formatDuration(seconds: number): string {
    const mins = Math.floor(seconds / 60);
    const secs = Math.floor(seconds % 60);
    return `${mins}:${secs.toString().padStart(2, '0')}`;
}
</script>

<template>
    <div class="bg-gray-800 rounded-lg shadow-xl overflow-hidden flex flex-col max-h-96">
        <!-- Header -->
        <div class="flex items-center justify-between px-4 py-3 border-b border-gray-700">
            <h3 class="text-lg font-semibold text-white">Queue</h3>
            <div class="flex items-center gap-2">
                <button
                    v-if="!isEmpty"
                    @click="handleClearQueue"
                    class="text-sm text-gray-400 hover:text-white transition-colors"
                >
                    Clear
                </button>
                <button
                    @click="emit('close')"
                    class="p-1 text-gray-400 hover:text-white transition-colors"
                    aria-label="Close queue"
                >
                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" />
                    </svg>
                </button>
            </div>
        </div>

        <!-- Queue list -->
        <div v-if="!isEmpty" class="flex-1 overflow-y-auto">
            <div
                v-for="(item, index) in queue"
                :key="item.id"
                :draggable="true"
                @dragstart="handleDragStart($event, item)"
                @dragover="handleDragOver($event, item)"
                @dragleave="handleDragLeave"
                @drop="handleDrop"
                @dragend="handleDragEnd"
                :class="[
                    'flex items-center gap-3 px-4 py-2 hover:bg-gray-700/50 cursor-pointer group transition-colors',
                    index === currentIndex ? 'bg-gray-700/50' : '',
                    dragOverItem === item.id ? 'border-t-2 border-green-500' : '',
                    draggedItem === item.id ? 'opacity-50' : '',
                ]"
                @click="handlePlayItem(item, index)"
            >
                <!-- Drag handle -->
                <div class="text-gray-500 cursor-grab active:cursor-grabbing">
                    <svg class="w-4 h-4" fill="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                        <path d="M11 18c0 1.1-.9 2-2 2s-2-.9-2-2 .9-2 2-2 2 .9 2 2zm-2-8c-1.1 0-2 .9-2 2s.9 2 2 2 2-.9 2-2-.9-2-2-2zm0-6c-1.1 0-2 .9-2 2s.9 2 2 2 2-.9 2-2-.9-2-2-2zm6 4c1.1 0 2-.9 2-2s-.9-2-2-2-2 .9-2 2 .9 2 2 2zm0 2c-1.1 0-2 .9-2 2s.9 2 2 2 2-.9 2-2-.9-2-2-2zm0 6c-1.1 0-2 .9-2 2s.9 2 2 2 2-.9 2-2-.9-2-2-2z" />
                    </svg>
                </div>

                <!-- Playing indicator or index -->
                <div class="w-6 text-center">
                    <span
                        v-if="index === currentIndex && isPlaying"
                        class="text-green-500"
                    >
                        <svg class="w-4 h-4 animate-pulse" fill="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                            <path d="M8 5v14l11-7z" />
                        </svg>
                    </span>
                    <span v-else class="text-gray-500 text-sm">{{ index + 1 }}</span>
                </div>

                <!-- Song info -->
                <div class="flex-1 min-w-0">
                    <p
                        class="text-sm truncate"
                        :class="index === currentIndex ? 'text-green-500 font-medium' : 'text-white'"
                    >
                        {{ item.song.title }}
                    </p>
                    <p class="text-xs text-gray-400 truncate">
                        {{ item.song.artist?.name ?? 'Unknown Artist' }}
                    </p>
                </div>

                <!-- Duration -->
                <span class="text-xs text-gray-400 tabular-nums">
                    {{ formatDuration(item.song.duration) }}
                </span>

                <!-- Remove button -->
                <button
                    @click.stop="handleRemoveItem(item)"
                    class="p-1 text-gray-500 hover:text-white opacity-0 group-hover:opacity-100 transition-opacity"
                    aria-label="Remove from queue"
                >
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" />
                    </svg>
                </button>
            </div>
        </div>

        <!-- Empty state -->
        <div v-else class="flex-1 flex items-center justify-center py-12">
            <div class="text-center">
                <svg class="w-12 h-12 text-gray-600 mx-auto mb-3" fill="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                    <path d="M15 6H3v2h12V6zm0 4H3v2h12v-2zM3 16h8v-2H3v2zM17 6v8.18c-.31-.11-.65-.18-1-.18-1.66 0-3 1.34-3 3s1.34 3 3 3 3-1.34 3-3V8h3V6h-5z" />
                </svg>
                <p class="text-gray-400">Queue is empty</p>
                <p class="text-gray-500 text-sm mt-1">Add songs to get started</p>
            </div>
        </div>
    </div>
</template>

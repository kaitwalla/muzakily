<script setup lang="ts">
import { computed } from 'vue';
import { usePlayer } from '@/composables';

const { volume, isMuted, setVolume, toggleMute } = usePlayer();

const volumeIcon = computed(() => {
    if (isMuted.value || volume.value === 0) return 'muted';
    if (volume.value < 0.5) return 'low';
    return 'high';
});

function handleVolumeChange(event: Event): void {
    const target = event.target as HTMLInputElement;
    setVolume(parseFloat(target.value));
}
</script>

<template>
    <div class="flex items-center gap-2">
        <button
            @click="toggleMute"
            class="p-2 text-gray-400 hover:text-white transition-colors"
            :aria-label="isMuted ? 'Unmute' : 'Mute'"
            :aria-pressed="isMuted"
        >
            <!-- Muted icon -->
            <svg
                v-if="volumeIcon === 'muted'"
                class="w-5 h-5"
                fill="currentColor"
                viewBox="0 0 24 24"
                aria-hidden="true"
            >
                <path d="M16.5 12c0-1.77-1.02-3.29-2.5-4.03v2.21l2.45 2.45c.03-.2.05-.41.05-.63zm2.5 0c0 .94-.2 1.82-.54 2.64l1.51 1.51C20.63 14.91 21 13.5 21 12c0-4.28-2.99-7.86-7-8.77v2.06c2.89.86 5 3.54 5 6.71zM4.27 3L3 4.27 7.73 9H3v6h4l5 5v-6.73l4.25 4.25c-.67.52-1.42.93-2.25 1.18v2.06c1.38-.31 2.63-.95 3.69-1.81L19.73 21 21 19.73l-9-9L4.27 3zM12 4L9.91 6.09 12 8.18V4z" />
            </svg>
            <!-- Low volume icon -->
            <svg
                v-else-if="volumeIcon === 'low'"
                class="w-5 h-5"
                fill="currentColor"
                viewBox="0 0 24 24"
                aria-hidden="true"
            >
                <path d="M18.5 12c0-1.77-1.02-3.29-2.5-4.03v8.05c1.48-.73 2.5-2.25 2.5-4.02zM5 9v6h4l5 5V4L9 9H5z" />
            </svg>
            <!-- High volume icon -->
            <svg
                v-else
                class="w-5 h-5"
                fill="currentColor"
                viewBox="0 0 24 24"
                aria-hidden="true"
            >
                <path d="M3 9v6h4l5 5V4L7 9H3zm13.5 3c0-1.77-1.02-3.29-2.5-4.03v8.05c1.48-.73 2.5-2.25 2.5-4.02zM14 3.23v2.06c2.89.86 5 3.54 5 6.71s-2.11 5.85-5 6.71v2.06c4.01-.91 7-4.49 7-8.77s-2.99-7.86-7-8.77z" />
            </svg>
        </button>
        <input
            type="range"
            min="0"
            max="1"
            step="0.01"
            :value="volume"
            @input="handleVolumeChange"
            aria-label="Volume"
            class="w-24 h-1 bg-gray-600 rounded-full appearance-none cursor-pointer
                [&::-webkit-slider-thumb]:appearance-none
                [&::-webkit-slider-thumb]:w-3
                [&::-webkit-slider-thumb]:h-3
                [&::-webkit-slider-thumb]:bg-white
                [&::-webkit-slider-thumb]:rounded-full
                [&::-webkit-slider-thumb]:cursor-pointer
                [&::-moz-range-thumb]:w-3
                [&::-moz-range-thumb]:h-3
                [&::-moz-range-thumb]:bg-white
                [&::-moz-range-thumb]:rounded-full
                [&::-moz-range-thumb]:border-0
                [&::-moz-range-thumb]:cursor-pointer"
        />
    </div>
</template>

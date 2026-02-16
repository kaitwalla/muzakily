<script setup lang="ts">
import { ref, onMounted } from 'vue';
import { useAudio, usePlayer, useKeyboardShortcuts } from '@/composables';
import { getDownloadUrl } from '@/api/songs';
import PlayPauseButton from './PlayPauseButton.vue';
import VolumeControl from './VolumeControl.vue';
import ProgressBar from './ProgressBar.vue';
import NowPlaying from './NowPlaying.vue';
import QueuePanel from './QueuePanel.vue';
import DevicePicker from './DevicePicker.vue';

const { audioRef, registerAudioElement } = useAudio({ autoRegister: false });
const { currentSong, isShuffled, repeatMode, hasNext, hasPrevious, next, previous, toggleShuffle, cycleRepeatMode } = usePlayer();

function handleDownload(): void {
    if (currentSong.value) {
        const url = getDownloadUrl(currentSong.value.id);
        window.open(url, '_blank');
    }
}

// Initialize keyboard shortcuts
useKeyboardShortcuts();

const showQueue = ref(false);
const showDevices = ref(false);

onMounted(() => {
    registerAudioElement();
});

function toggleQueue(): void {
    showQueue.value = !showQueue.value;
    if (showQueue.value) {
        showDevices.value = false;
    }
}

function toggleDevices(): void {
    showDevices.value = !showDevices.value;
    if (showDevices.value) {
        showQueue.value = false;
    }
}
</script>

<template>
    <footer class="bg-gray-800 border-t border-gray-700 px-6 py-3 relative">
        <!-- Hidden audio element -->
        <audio ref="audioRef" />

        <div class="flex items-center gap-4">
            <!-- Current song info -->
            <div class="w-64">
                <NowPlaying />
            </div>

            <!-- Playback controls -->
            <div class="flex-1 flex flex-col items-center gap-1">
                <div class="flex items-center gap-4">
                    <!-- Shuffle button -->
                    <button
                        @click="toggleShuffle"
                        class="p-2 hover:bg-gray-700 rounded-full transition-colors"
                        :class="isShuffled ? 'text-green-500' : 'text-gray-400'"
                        aria-label="Shuffle"
                        :aria-pressed="isShuffled"
                    >
                        <svg class="w-4 h-4" fill="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                            <path d="M10.59 9.17L5.41 4 4 5.41l5.17 5.17 1.42-1.41zM14.5 4l2.04 2.04L4 18.59 5.41 20 17.96 7.46 20 9.5V4h-5.5zm.33 9.41l-1.41 1.41 3.13 3.13L14.5 20H20v-5.5l-2.04 2.04-3.13-3.13z"/>
                        </svg>
                    </button>

                    <!-- Previous button -->
                    <button
                        @click="previous"
                        :disabled="!hasPrevious"
                        class="p-2 text-gray-400 hover:text-white hover:bg-gray-700 rounded-full transition-colors disabled:opacity-50 disabled:cursor-not-allowed"
                        aria-label="Previous track"
                    >
                        <svg class="w-5 h-5" fill="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                            <path d="M6 6h2v12H6zm3.5 6l8.5 6V6z"/>
                        </svg>
                    </button>

                    <!-- Play/Pause button -->
                    <PlayPauseButton size="lg" variant="primary" />

                    <!-- Next button -->
                    <button
                        @click="next"
                        :disabled="!hasNext"
                        class="p-2 text-gray-400 hover:text-white hover:bg-gray-700 rounded-full transition-colors disabled:opacity-50 disabled:cursor-not-allowed"
                        aria-label="Next track"
                    >
                        <svg class="w-5 h-5" fill="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                            <path d="M6 18l8.5-6L6 6v12zM16 6v12h2V6h-2z"/>
                        </svg>
                    </button>

                    <!-- Repeat button -->
                    <button
                        @click="cycleRepeatMode"
                        class="p-2 hover:bg-gray-700 rounded-full transition-colors"
                        :class="repeatMode !== 'off' ? 'text-green-500' : 'text-gray-400'"
                        :aria-label="repeatMode === 'one' ? 'Repeat one' : repeatMode === 'all' ? 'Repeat all' : 'Repeat off'"
                        :aria-pressed="repeatMode !== 'off'"
                    >
                        <svg v-if="repeatMode === 'one'" class="w-4 h-4" fill="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                            <path d="M7 7h10v3l4-4-4-4v3H5v6h2V7zm10 10H7v-3l-4 4 4 4v-3h12v-6h-2v4zm-4-2V9h-1l-2 1v1h1.5v4H13z"/>
                        </svg>
                        <svg v-else class="w-4 h-4" fill="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                            <path d="M7 7h10v3l4-4-4-4v3H5v6h2V7zm10 10H7v-3l-4 4 4 4v-3h12v-6h-2v4z"/>
                        </svg>
                    </button>
                </div>

                <!-- Progress bar -->
                <div class="w-full max-w-xl">
                    <ProgressBar />
                </div>
            </div>

            <!-- Right side controls -->
            <div class="w-64 flex items-center justify-end gap-2">
                <!-- Queue button -->
                <button
                    @click="toggleQueue"
                    class="p-2 hover:bg-gray-700 rounded-full transition-colors"
                    :class="showQueue ? 'text-green-500' : 'text-gray-400'"
                    aria-label="Queue"
                    :aria-pressed="showQueue"
                >
                    <svg class="w-5 h-5" fill="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                        <path d="M15 6H3v2h12V6zm0 4H3v2h12v-2zM3 16h8v-2H3v2zM17 6v8.18c-.31-.11-.65-.18-1-.18-1.66 0-3 1.34-3 3s1.34 3 3 3 3-1.34 3-3V8h3V6h-5z"/>
                    </svg>
                </button>

                <!-- Device picker button -->
                <button
                    @click="toggleDevices"
                    class="p-2 hover:bg-gray-700 rounded-full transition-colors"
                    :class="showDevices ? 'text-green-500' : 'text-gray-400'"
                    aria-label="Devices"
                    :aria-pressed="showDevices"
                >
                    <svg class="w-5 h-5" fill="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                        <path d="M21 3H3c-1.1 0-2 .9-2 2v14c0 1.1.9 2 2 2h18c1.1 0 2-.9 2-2V5c0-1.1-.9-2-2-2zm0 16H3V5h18v14zM9 8h2v8H9zm4 0h2v8h-2z"/>
                    </svg>
                </button>

                <!-- Download button -->
                <button
                    @click="handleDownload"
                    :disabled="!currentSong"
                    class="p-2 text-gray-400 hover:text-white hover:bg-gray-700 rounded-full transition-colors disabled:opacity-50 disabled:cursor-not-allowed"
                    aria-label="Download current song"
                >
                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-4l-4 4m0 0l-4-4m4 4V4"/>
                    </svg>
                </button>

                <!-- Volume control -->
                <VolumeControl />
            </div>
        </div>

        <!-- Queue panel overlay -->
        <Teleport to="body">
            <div
                v-if="showQueue"
                class="fixed inset-0 z-40"
                @click="showQueue = false"
            />
        </Teleport>
        <div
            v-if="showQueue"
            class="absolute bottom-full right-6 mb-2 z-50 w-96"
        >
            <QueuePanel @close="showQueue = false" />
        </div>

        <!-- Device picker overlay -->
        <Teleport to="body">
            <div
                v-if="showDevices"
                class="fixed inset-0 z-40"
                @click="showDevices = false"
            />
        </Teleport>
        <div
            v-if="showDevices"
            class="absolute bottom-full right-48 mb-2 z-50"
        >
            <DevicePicker @close="showDevices = false" />
        </div>
    </footer>
</template>

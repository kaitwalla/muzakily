<script setup lang="ts">
import { ref, onMounted, computed } from 'vue';
import { usePlayerStore } from '@/stores/player';

const playerStore = usePlayerStore();
const audioRef = ref<HTMLAudioElement | null>(null);

onMounted(() => {
    if (audioRef.value) {
        playerStore.setAudioElement(audioRef.value);
    }
});

const progressPercent = computed(() => {
    if (playerStore.duration === 0) return 0;
    return (playerStore.currentTime / playerStore.duration) * 100;
});

function formatTime(seconds: number): string {
    const mins = Math.floor(seconds / 60);
    const secs = Math.floor(seconds % 60);
    return `${mins}:${secs.toString().padStart(2, '0')}`;
}

function handleProgressClick(event: MouseEvent): void {
    const target = event.currentTarget as HTMLElement;
    const rect = target.getBoundingClientRect();
    const percent = (event.clientX - rect.left) / rect.width;
    playerStore.seek(percent * playerStore.duration);
}

function handleVolumeChange(event: Event): void {
    const target = event.target as HTMLInputElement;
    playerStore.setVolume(parseFloat(target.value));
}
</script>

<template>
    <footer class="bg-gray-800 border-t border-gray-700 px-6 py-3">
        <audio ref="audioRef" />

        <div class="flex items-center gap-4">
            <!-- Current song info -->
            <div class="w-64 flex items-center gap-3">
                <template v-if="playerStore.currentSong">
                    <div
                        class="w-12 h-12 bg-gray-700 rounded flex-shrink-0"
                        :style="playerStore.currentSong.album?.cover_url ? { backgroundImage: `url(${playerStore.currentSong.album.cover_url})`, backgroundSize: 'cover' } : {}"
                    />
                    <div class="min-w-0">
                        <p class="text-sm text-white truncate">{{ playerStore.currentSong.title }}</p>
                        <p class="text-xs text-gray-400 truncate">{{ playerStore.currentSong.artist?.name }}</p>
                    </div>
                </template>
                <template v-else>
                    <div class="w-12 h-12 bg-gray-700 rounded flex-shrink-0" />
                    <div class="min-w-0">
                        <p class="text-sm text-gray-500">No song playing</p>
                    </div>
                </template>
            </div>

            <!-- Playback controls -->
            <div class="flex-1 flex flex-col items-center gap-1">
                <div class="flex items-center gap-4">
                    <button
                        @click="playerStore.toggleShuffle"
                        class="p-2 hover:bg-gray-700 rounded-full transition-colors"
                        :class="playerStore.isShuffled ? 'text-green-500' : 'text-gray-400'"
                        aria-label="Shuffle"
                        :aria-pressed="playerStore.isShuffled"
                    >
                        <svg class="w-4 h-4" fill="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                            <path d="M10.59 9.17L5.41 4 4 5.41l5.17 5.17 1.42-1.41zM14.5 4l2.04 2.04L4 18.59 5.41 20 17.96 7.46 20 9.5V4h-5.5zm.33 9.41l-1.41 1.41 3.13 3.13L14.5 20H20v-5.5l-2.04 2.04-3.13-3.13z"/>
                        </svg>
                    </button>

                    <button
                        @click="playerStore.previous"
                        :disabled="!playerStore.hasPrevious"
                        class="p-2 text-gray-400 hover:text-white hover:bg-gray-700 rounded-full transition-colors disabled:opacity-50 disabled:cursor-not-allowed"
                        aria-label="Previous track"
                    >
                        <svg class="w-5 h-5" fill="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                            <path d="M6 6h2v12H6zm3.5 6l8.5 6V6z"/>
                        </svg>
                    </button>

                    <button
                        @click="playerStore.togglePlayPause"
                        :disabled="!playerStore.currentSong"
                        class="p-3 bg-white text-gray-900 rounded-full hover:scale-105 transition-transform disabled:opacity-50 disabled:cursor-not-allowed"
                        :aria-label="playerStore.isPlaying ? 'Pause' : 'Play'"
                    >
                        <svg v-if="playerStore.isPlaying" class="w-5 h-5" fill="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                            <path d="M6 19h4V5H6v14zm8-14v14h4V5h-4z"/>
                        </svg>
                        <svg v-else class="w-5 h-5" fill="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                            <path d="M8 5v14l11-7z"/>
                        </svg>
                    </button>

                    <button
                        @click="playerStore.next"
                        :disabled="!playerStore.hasNext"
                        class="p-2 text-gray-400 hover:text-white hover:bg-gray-700 rounded-full transition-colors disabled:opacity-50 disabled:cursor-not-allowed"
                        aria-label="Next track"
                    >
                        <svg class="w-5 h-5" fill="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                            <path d="M6 18l8.5-6L6 6v12zM16 6v12h2V6h-2z"/>
                        </svg>
                    </button>

                    <button
                        @click="playerStore.cycleRepeatMode"
                        class="p-2 hover:bg-gray-700 rounded-full transition-colors"
                        :class="playerStore.repeatMode !== 'off' ? 'text-green-500' : 'text-gray-400'"
                        :aria-label="playerStore.repeatMode === 'one' ? 'Repeat one' : playerStore.repeatMode === 'all' ? 'Repeat all' : 'Repeat off'"
                        :aria-pressed="playerStore.repeatMode !== 'off'"
                    >
                        <svg v-if="playerStore.repeatMode === 'one'" class="w-4 h-4" fill="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                            <path d="M7 7h10v3l4-4-4-4v3H5v6h2V7zm10 10H7v-3l-4 4 4 4v-3h12v-6h-2v4zm-4-2V9h-1l-2 1v1h1.5v4H13z"/>
                        </svg>
                        <svg v-else class="w-4 h-4" fill="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                            <path d="M7 7h10v3l4-4-4-4v3H5v6h2V7zm10 10H7v-3l-4 4 4 4v-3h12v-6h-2v4z"/>
                        </svg>
                    </button>
                </div>

                <!-- Progress bar -->
                <div class="w-full max-w-xl flex items-center gap-2">
                    <span class="text-xs text-gray-400 w-10 text-right">
                        {{ formatTime(playerStore.currentTime) }}
                    </span>
                    <div
                        class="flex-1 h-1 bg-gray-600 rounded-full cursor-pointer group"
                        @click="handleProgressClick"
                    >
                        <div
                            class="h-full bg-white rounded-full group-hover:bg-green-500 transition-colors relative"
                            :style="{ width: `${progressPercent}%` }"
                        >
                            <div class="absolute right-0 top-1/2 -translate-y-1/2 w-3 h-3 bg-white rounded-full opacity-0 group-hover:opacity-100 transition-opacity" />
                        </div>
                    </div>
                    <span class="text-xs text-gray-400 w-10">
                        {{ formatTime(playerStore.duration) }}
                    </span>
                </div>
            </div>

            <!-- Volume controls -->
            <div class="w-40 flex items-center gap-2">
                <button
                    @click="playerStore.toggleMute"
                    class="p-2 text-gray-400 hover:text-white transition-colors"
                    :aria-label="playerStore.isMuted ? 'Unmute' : 'Mute'"
                    :aria-pressed="playerStore.isMuted"
                >
                    <svg v-if="playerStore.isMuted || playerStore.volume === 0" class="w-5 h-5" fill="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                        <path d="M16.5 12c0-1.77-1.02-3.29-2.5-4.03v2.21l2.45 2.45c.03-.2.05-.41.05-.63zm2.5 0c0 .94-.2 1.82-.54 2.64l1.51 1.51C20.63 14.91 21 13.5 21 12c0-4.28-2.99-7.86-7-8.77v2.06c2.89.86 5 3.54 5 6.71zM4.27 3L3 4.27 7.73 9H3v6h4l5 5v-6.73l4.25 4.25c-.67.52-1.42.93-2.25 1.18v2.06c1.38-.31 2.63-.95 3.69-1.81L19.73 21 21 19.73l-9-9L4.27 3zM12 4L9.91 6.09 12 8.18V4z"/>
                    </svg>
                    <svg v-else-if="playerStore.volume < 0.5" class="w-5 h-5" fill="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                        <path d="M18.5 12c0-1.77-1.02-3.29-2.5-4.03v8.05c1.48-.73 2.5-2.25 2.5-4.02zM5 9v6h4l5 5V4L9 9H5z"/>
                    </svg>
                    <svg v-else class="w-5 h-5" fill="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                        <path d="M3 9v6h4l5 5V4L7 9H3zm13.5 3c0-1.77-1.02-3.29-2.5-4.03v8.05c1.48-.73 2.5-2.25 2.5-4.02zM14 3.23v2.06c2.89.86 5 3.54 5 6.71s-2.11 5.85-5 6.71v2.06c4.01-.91 7-4.49 7-8.77s-2.99-7.86-7-8.77z"/>
                    </svg>
                </button>
                <input
                    type="range"
                    min="0"
                    max="1"
                    step="0.01"
                    :value="playerStore.volume"
                    @input="handleVolumeChange"
                    aria-label="Volume"
                    class="flex-1 h-1 bg-gray-600 rounded-full appearance-none cursor-pointer [&::-webkit-slider-thumb]:appearance-none [&::-webkit-slider-thumb]:w-3 [&::-webkit-slider-thumb]:h-3 [&::-webkit-slider-thumb]:bg-white [&::-webkit-slider-thumb]:rounded-full"
                />
            </div>
        </div>
    </footer>
</template>

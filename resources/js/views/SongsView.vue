<script setup lang="ts">
import { onMounted } from 'vue';
import { useSongsStore } from '@/stores/songs';
import { usePlayerStore } from '@/stores/player';
import type { Song } from '@/types/models';

const songsStore = useSongsStore();
const playerStore = usePlayerStore();

onMounted(() => {
    songsStore.fetchSongs();
});

function playSong(_song: Song, index: number): void {
    playerStore.play(songsStore.songs, index);
}

function playAllSongs(): void {
    if (songsStore.songs.length > 0) {
        playerStore.play(songsStore.songs, 0);
    }
}

function formatDuration(seconds: number): string {
    const mins = Math.floor(seconds / 60);
    const secs = Math.floor(seconds % 60);
    return `${mins}:${secs.toString().padStart(2, '0')}`;
}
</script>

<template>
    <div>
        <div class="flex items-center justify-between mb-6">
            <h1 class="text-3xl font-bold text-white">Songs</h1>
            <button
                v-if="songsStore.hasSongs"
                @click="playAllSongs"
                class="px-4 py-2 bg-green-500 hover:bg-green-600 text-white font-semibold rounded-full transition-colors"
            >
                Play All
            </button>
        </div>

        <div v-if="songsStore.loading && !songsStore.hasSongs" class="text-center py-12">
            <p class="text-gray-400">Loading songs...</p>
        </div>

        <div v-else-if="songsStore.error" class="text-center py-12">
            <p class="text-red-400">{{ songsStore.error }}</p>
        </div>

        <div v-else-if="!songsStore.hasSongs" class="text-center py-12">
            <p class="text-gray-400">No songs found</p>
        </div>

        <div v-else class="bg-gray-800 rounded-lg overflow-hidden">
            <table class="w-full">
                <thead class="border-b border-gray-700">
                    <tr class="text-left text-sm text-gray-400">
                        <th class="px-4 py-3 w-12">#</th>
                        <th class="px-4 py-3">Title</th>
                        <th class="px-4 py-3">Artist</th>
                        <th class="px-4 py-3">Album</th>
                        <th class="px-4 py-3 text-right">Duration</th>
                    </tr>
                </thead>
                <tbody>
                    <tr
                        v-for="(song, index) in songsStore.songs"
                        :key="song.id"
                        @click="playSong(song, index)"
                        class="hover:bg-gray-700 cursor-pointer transition-colors group"
                        :class="{ 'bg-gray-700': playerStore.currentSong?.id === song.id }"
                    >
                        <td class="px-4 py-3 text-gray-400">
                            <span v-if="playerStore.currentSong?.id === song.id && playerStore.isPlaying" class="text-green-500">
                                <svg class="w-4 h-4 animate-pulse" fill="currentColor" viewBox="0 0 24 24">
                                    <path d="M8 5v14l11-7z"/>
                                </svg>
                            </span>
                            <span v-else>{{ index + 1 }}</span>
                        </td>
                        <td class="px-4 py-3">
                            <p class="text-white font-medium" :class="{ 'text-green-500': playerStore.currentSong?.id === song.id }">
                                {{ song.title }}
                            </p>
                        </td>
                        <td class="px-4 py-3 text-gray-400">{{ song.artist?.name ?? 'Unknown' }}</td>
                        <td class="px-4 py-3 text-gray-400">{{ song.album?.title ?? '-' }}</td>
                        <td class="px-4 py-3 text-gray-400 text-right">{{ formatDuration(song.duration) }}</td>
                    </tr>
                </tbody>
            </table>
        </div>

        <div v-if="songsStore.hasMore" class="mt-6 text-center">
            <button
                @click="songsStore.loadMore"
                :disabled="songsStore.loading"
                class="px-6 py-2 bg-gray-700 hover:bg-gray-600 text-white rounded-full transition-colors disabled:opacity-50"
            >
                {{ songsStore.loading ? 'Loading...' : 'Load More' }}
            </button>
        </div>
    </div>
</template>

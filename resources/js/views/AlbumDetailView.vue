<script setup lang="ts">
import { onMounted, watch } from 'vue';
import { useRoute, RouterLink } from 'vue-router';
import { useAlbumsStore } from '@/stores/albums';
import { usePlayerStore } from '@/stores/player';
import type { Song } from '@/types/models';

const route = useRoute();
const albumsStore = useAlbumsStore();
const playerStore = usePlayerStore();

async function loadAlbum(): Promise<void> {
    const slug = route.params.slug as string;
    const album = await albumsStore.fetchAlbum(slug);
    await albumsStore.fetchAlbumSongs(album.id);
}

onMounted(() => {
    loadAlbum();
});

watch(() => route.params.slug, () => {
    loadAlbum();
});

function playAlbum(): void {
    if (albumsStore.currentAlbumSongs.length > 0) {
        playerStore.play(albumsStore.currentAlbumSongs, 0);
    }
}

function playSong(_song: Song, index: number): void {
    playerStore.play(albumsStore.currentAlbumSongs, index);
}

function formatDuration(seconds: number): string {
    const mins = Math.floor(seconds / 60);
    const secs = Math.floor(seconds % 60);
    return `${mins}:${secs.toString().padStart(2, '0')}`;
}

function getTotalDuration(): string {
    const total = albumsStore.currentAlbumSongs.reduce((sum, song) => sum + song.duration, 0);
    const hours = Math.floor(total / 3600);
    const mins = Math.floor((total % 3600) / 60);
    if (hours > 0) {
        return `${hours} hr ${mins} min`;
    }
    return `${mins} min`;
}
</script>

<template>
    <div>
        <div v-if="albumsStore.loading && !albumsStore.currentAlbum" class="text-center py-12">
            <p class="text-gray-400">Loading album...</p>
        </div>

        <div v-else-if="albumsStore.error" class="text-center py-12">
            <p class="text-red-400">{{ albumsStore.error }}</p>
        </div>

        <template v-else-if="albumsStore.currentAlbum">
            <!-- Album Header -->
            <div class="flex gap-6 mb-8">
                <div class="w-56 h-56 bg-gray-700 rounded-lg overflow-hidden flex-shrink-0 shadow-xl">
                    <img
                        v-if="albumsStore.currentAlbum.cover_url"
                        :src="albumsStore.currentAlbum.cover_url"
                        :alt="albumsStore.currentAlbum.title"
                        class="w-full h-full object-cover"
                    />
                    <div v-else class="w-full h-full flex items-center justify-center">
                        <svg class="w-24 h-24 text-gray-600" fill="currentColor" viewBox="0 0 24 24">
                            <path d="M12 2C6.48 2 2 6.48 2 12s4.48 10 10 10 10-4.48 10-10S17.52 2 12 2zm0 14.5c-2.49 0-4.5-2.01-4.5-4.5S9.51 7.5 12 7.5s4.5 2.01 4.5 4.5-2.01 4.5-4.5 4.5zm0-5.5c-.55 0-1 .45-1 1s.45 1 1 1 1-.45 1-1-.45-1-1-1z"/>
                        </svg>
                    </div>
                </div>
                <div class="flex flex-col justify-end">
                    <p class="text-sm text-gray-400 uppercase font-medium">Album</p>
                    <h1 class="text-5xl font-bold text-white mt-2 mb-4">
                        {{ albumsStore.currentAlbum.title }}
                    </h1>
                    <div class="flex items-center gap-2 text-gray-300">
                        <RouterLink
                            v-if="albumsStore.currentAlbum.artist"
                            :to="{ name: 'artist-detail', params: { slug: albumsStore.currentAlbum.artist.slug } }"
                            class="font-medium hover:underline"
                        >
                            {{ albumsStore.currentAlbum.artist.name }}
                        </RouterLink>
                        <span v-if="albumsStore.currentAlbum.release_date" class="text-gray-400">
                            &bull; {{ new Date(albumsStore.currentAlbum.release_date).getFullYear() }}
                        </span>
                        <span class="text-gray-400">
                            &bull; {{ albumsStore.currentAlbumSongs.length }} songs, {{ getTotalDuration() }}
                        </span>
                    </div>
                </div>
            </div>

            <!-- Play Button -->
            <div class="mb-6">
                <button
                    @click="playAlbum"
                    :disabled="albumsStore.currentAlbumSongs.length === 0"
                    class="w-14 h-14 bg-green-500 hover:bg-green-400 hover:scale-105 rounded-full flex items-center justify-center transition-all disabled:opacity-50"
                >
                    <svg class="w-6 h-6 text-black ml-1" fill="currentColor" viewBox="0 0 24 24">
                        <path d="M8 5v14l11-7z"/>
                    </svg>
                </button>
            </div>

            <!-- Songs List -->
            <div class="bg-gray-800/50 rounded-lg overflow-hidden">
                <table class="w-full">
                    <thead class="border-b border-gray-700">
                        <tr class="text-left text-sm text-gray-400">
                            <th class="px-4 py-3 w-12">#</th>
                            <th class="px-4 py-3">Title</th>
                            <th class="px-4 py-3 text-right">Duration</th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr
                            v-for="(song, index) in albumsStore.currentAlbumSongs"
                            :key="song.id"
                            @click="playSong(song, index)"
                            class="hover:bg-gray-700/50 cursor-pointer transition-colors group"
                            :class="{ 'bg-gray-700/50': playerStore.currentSong?.id === song.id }"
                        >
                            <td class="px-4 py-3 text-gray-400">
                                <span v-if="playerStore.currentSong?.id === song.id && playerStore.isPlaying" class="text-green-500">
                                    <svg class="w-4 h-4 animate-pulse" fill="currentColor" viewBox="0 0 24 24">
                                        <path d="M8 5v14l11-7z"/>
                                    </svg>
                                </span>
                                <span v-else>{{ song.track_number ?? index + 1 }}</span>
                            </td>
                            <td class="px-4 py-3">
                                <p class="text-white font-medium" :class="{ 'text-green-500': playerStore.currentSong?.id === song.id }">
                                    {{ song.title }}
                                </p>
                            </td>
                            <td class="px-4 py-3 text-gray-400 text-right">
                                {{ formatDuration(song.duration) }}
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>
        </template>
    </div>
</template>

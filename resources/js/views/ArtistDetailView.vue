<script setup lang="ts">
import { onMounted, watch } from 'vue';
import { useRoute, RouterLink } from 'vue-router';
import { useArtistsStore } from '@/stores/artists';
import { usePlayerStore } from '@/stores/player';
import type { Song } from '@/types/models';

const route = useRoute();
const artistsStore = useArtistsStore();
const playerStore = usePlayerStore();

async function loadArtist(): Promise<void> {
    const slug = route.params.slug as string;
    const artist = await artistsStore.fetchArtist(slug);
    await Promise.all([
        artistsStore.fetchArtistAlbums(artist.id),
        artistsStore.fetchArtistSongs(artist.id),
    ]);
}

onMounted(() => {
    loadArtist();
});

watch(() => route.params.slug, () => {
    loadArtist();
});

function playAllSongs(): void {
    if (artistsStore.currentArtistSongs.length > 0) {
        playerStore.play(artistsStore.currentArtistSongs, 0);
    }
}

function playSong(_song: Song, index: number): void {
    playerStore.play(artistsStore.currentArtistSongs, index);
}

function formatDuration(seconds: number): string {
    const mins = Math.floor(seconds / 60);
    const secs = Math.floor(seconds % 60);
    return `${mins}:${secs.toString().padStart(2, '0')}`;
}
</script>

<template>
    <div>
        <div v-if="artistsStore.loading && !artistsStore.currentArtist" class="text-center py-12">
            <p class="text-gray-400">Loading artist...</p>
        </div>

        <div v-else-if="artistsStore.error" class="text-center py-12">
            <p class="text-red-400">{{ artistsStore.error }}</p>
        </div>

        <template v-else-if="artistsStore.currentArtist">
            <!-- Artist Header -->
            <div class="flex gap-6 mb-8">
                <div class="w-48 h-48 bg-gray-700 rounded-full overflow-hidden flex-shrink-0 shadow-xl">
                    <img
                        v-if="artistsStore.currentArtist.image_url"
                        :src="artistsStore.currentArtist.image_url"
                        :alt="artistsStore.currentArtist.name"
                        class="w-full h-full object-cover"
                    />
                    <div v-else class="w-full h-full flex items-center justify-center">
                        <svg class="w-24 h-24 text-gray-600" fill="currentColor" viewBox="0 0 24 24">
                            <path d="M12 12c2.21 0 4-1.79 4-4s-1.79-4-4-4-4 1.79-4 4 1.79 4 4 4zm0 2c-2.67 0-8 1.34-8 4v2h16v-2c0-2.66-5.33-4-8-4z"/>
                        </svg>
                    </div>
                </div>
                <div class="flex flex-col justify-end">
                    <p class="text-sm text-gray-400 uppercase font-medium">Artist</p>
                    <h1 class="text-5xl font-bold text-white mt-2 mb-4">
                        {{ artistsStore.currentArtist.name }}
                    </h1>
                    <p v-if="artistsStore.currentArtist.bio" class="text-gray-300 max-w-2xl line-clamp-2">
                        {{ artistsStore.currentArtist.bio }}
                    </p>
                </div>
            </div>

            <!-- Play Button -->
            <div class="mb-8">
                <button
                    @click="playAllSongs"
                    :disabled="artistsStore.currentArtistSongs.length === 0"
                    class="w-14 h-14 bg-green-500 hover:bg-green-400 hover:scale-105 rounded-full flex items-center justify-center transition-all disabled:opacity-50"
                >
                    <svg class="w-6 h-6 text-black ml-1" fill="currentColor" viewBox="0 0 24 24">
                        <path d="M8 5v14l11-7z"/>
                    </svg>
                </button>
            </div>

            <!-- Popular Songs -->
            <section v-if="artistsStore.currentArtistSongs.length > 0" class="mb-8">
                <h2 class="text-xl font-semibold text-white mb-4">Popular</h2>
                <div class="bg-gray-800/50 rounded-lg overflow-hidden">
                    <table class="w-full">
                        <tbody>
                            <tr
                                v-for="(song, index) in artistsStore.currentArtistSongs.slice(0, 5)"
                                :key="song.id"
                                @click="playSong(song, index)"
                                class="hover:bg-gray-700/50 cursor-pointer transition-colors group"
                                :class="{ 'bg-gray-700/50': playerStore.currentSong?.id === song.id }"
                            >
                                <td class="px-4 py-3 w-12 text-gray-400">
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
                                    <p class="text-gray-400 text-sm">{{ song.album?.title ?? '-' }}</p>
                                </td>
                                <td class="px-4 py-3 text-gray-400 text-right">
                                    {{ formatDuration(song.duration) }}
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </section>

            <!-- Albums -->
            <section v-if="artistsStore.currentArtistAlbums.length > 0">
                <h2 class="text-xl font-semibold text-white mb-4">Albums</h2>
                <div class="grid grid-cols-2 md:grid-cols-3 lg:grid-cols-4 xl:grid-cols-5 gap-4">
                    <RouterLink
                        v-for="album in artistsStore.currentArtistAlbums"
                        :key="album.id"
                        :to="{ name: 'album-detail', params: { slug: album.slug } }"
                        class="bg-gray-800 rounded-lg p-4 hover:bg-gray-700 transition-colors group"
                    >
                        <div class="aspect-square bg-gray-700 rounded-lg mb-3 overflow-hidden">
                            <img
                                v-if="album.cover_url"
                                :src="album.cover_url"
                                :alt="album.title"
                                class="w-full h-full object-cover group-hover:scale-105 transition-transform"
                            />
                        </div>
                        <p class="text-white font-medium truncate">{{ album.title }}</p>
                        <p v-if="album.release_date" class="text-gray-500 text-sm">
                            {{ new Date(album.release_date).getFullYear() }}
                        </p>
                    </RouterLink>
                </div>
            </section>
        </template>
    </div>
</template>

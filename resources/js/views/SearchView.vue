<script setup lang="ts">
import { ref, watch, onMounted } from 'vue';
import { useRoute, useRouter, RouterLink } from 'vue-router';
import { useSearchStore } from '@/stores/search';
import { usePlayerStore } from '@/stores/player';
import { useDebounceFn } from '@vueuse/core';
import type { Song } from '@/types/models';

const route = useRoute();
const router = useRouter();
const searchStore = useSearchStore();
const playerStore = usePlayerStore();

const searchInput = ref('');

const debouncedSearch = useDebounceFn((query: string) => {
    if (query.trim()) {
        router.replace({ query: { q: query } });
        searchStore.search(query);
    } else {
        router.replace({ query: {} });
        searchStore.clearResults();
    }
}, 300);

watch(searchInput, (value) => {
    debouncedSearch(value);
});

onMounted(() => {
    const query = route.query.q as string | undefined;
    if (query) {
        searchInput.value = query;
        searchStore.search(query);
    }
});

function playSong(song: Song): void {
    playerStore.playSong(song);
}

function formatDuration(seconds: number): string {
    const mins = Math.floor(seconds / 60);
    const secs = Math.floor(seconds % 60);
    return `${mins}:${secs.toString().padStart(2, '0')}`;
}
</script>

<template>
    <div>
        <div class="mb-6">
            <input
                v-model="searchInput"
                type="search"
                placeholder="Search for songs, albums, artists..."
                class="w-full px-6 py-4 bg-surface-800 border border-surface-700 rounded-full text-white placeholder-surface-400 focus:outline-none focus:ring-2 focus:ring-green-500 focus:border-transparent text-lg"
            />
        </div>

        <div v-if="searchStore.loading" class="text-center py-12">
            <p class="text-surface-400">Searching...</p>
        </div>

        <div v-else-if="searchStore.error" class="text-center py-12">
            <p class="text-red-400">{{ searchStore.error }}</p>
        </div>

        <div v-else-if="searchStore.hasSearched && !searchStore.hasResults" class="text-center py-12">
            <p class="text-surface-400">No results found for "{{ searchStore.query }}"</p>
        </div>

        <div v-else-if="searchStore.hasResults">
            <!-- Songs -->
            <section v-if="searchStore.songs.length > 0" class="mb-8">
                <h2 class="text-xl font-semibold text-white mb-4">Songs</h2>
                <div class="bg-surface-800 rounded-lg overflow-hidden">
                    <table class="w-full">
                        <tbody>
                            <tr
                                v-for="song in searchStore.songs.slice(0, 5)"
                                :key="song.id"
                                @click="playSong(song)"
                                class="hover:bg-surface-700 cursor-pointer transition-colors"
                            >
                                <td class="px-4 py-3">
                                    <p class="text-white font-medium">{{ song.title }}</p>
                                    <RouterLink
                                        v-if="song.artist_slug"
                                        :to="{ name: 'artist-detail', params: { slug: song.artist_slug } }"
                                        class="text-surface-400 text-sm hover:text-white hover:underline"
                                        @click.stop
                                    >
                                        {{ song.artist_name }}
                                    </RouterLink>
                                    <p v-else class="text-surface-400 text-sm">{{ song.artist_name ?? 'Unknown' }}</p>
                                </td>
                                <td class="px-4 py-3 text-surface-400 text-right">
                                    {{ formatDuration(song.length) }}
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </section>

            <!-- Albums -->
            <section v-if="searchStore.albums.length > 0" class="mb-8">
                <h2 class="text-xl font-semibold text-white mb-4">Albums</h2>
                <div class="grid grid-cols-2 md:grid-cols-3 lg:grid-cols-4 xl:grid-cols-5 gap-4">
                    <RouterLink
                        v-for="album in searchStore.albums.slice(0, 5)"
                        :key="album.id"
                        :to="{ name: 'album-detail', params: { slug: album.id } }"
                        class="bg-surface-800 rounded-lg p-4 hover:bg-surface-700 transition-colors"
                    >
                        <div class="aspect-square bg-surface-700 rounded-lg mb-3 overflow-hidden">
                            <img
                                v-if="album.cover"
                                :src="album.cover"
                                :alt="album.name"
                                class="w-full h-full object-cover"
                            />
                        </div>
                        <p class="text-white font-medium truncate">{{ album.name }}</p>
                        <p class="text-surface-400 text-sm truncate">{{ album.artist_name ?? 'Unknown' }}</p>
                    </RouterLink>
                </div>
            </section>

            <!-- Artists -->
            <section v-if="searchStore.artists.length > 0" class="mb-8">
                <h2 class="text-xl font-semibold text-white mb-4">Artists</h2>
                <div class="grid grid-cols-2 md:grid-cols-3 lg:grid-cols-4 xl:grid-cols-5 gap-4">
                    <RouterLink
                        v-for="artist in searchStore.artists.slice(0, 5)"
                        :key="artist.id"
                        :to="{ name: 'artist-detail', params: { slug: artist.id } }"
                        class="bg-surface-800 rounded-lg p-4 hover:bg-surface-700 transition-colors text-center"
                    >
                        <div class="aspect-square bg-surface-700 rounded-full mb-3 mx-auto overflow-hidden w-24 h-24">
                            <img
                                v-if="artist.image"
                                :src="artist.image"
                                :alt="artist.name"
                                class="w-full h-full object-cover"
                            />
                        </div>
                        <p class="text-white font-medium truncate">{{ artist.name }}</p>
                        <p class="text-surface-500 text-sm">Artist</p>
                    </RouterLink>
                </div>
            </section>

            <!-- Playlists -->
            <section v-if="searchStore.playlists.length > 0" class="mb-8">
                <h2 class="text-xl font-semibold text-white mb-4">Playlists</h2>
                <div class="grid grid-cols-2 md:grid-cols-3 lg:grid-cols-4 xl:grid-cols-5 gap-4">
                    <RouterLink
                        v-for="playlist in searchStore.playlists.slice(0, 5)"
                        :key="playlist.id"
                        :to="{ name: 'playlist-detail', params: { slug: playlist.slug } }"
                        class="bg-surface-800 rounded-lg p-4 hover:bg-surface-700 transition-colors"
                    >
                        <div class="aspect-square bg-surface-700 rounded-lg mb-3 overflow-hidden">
                            <img
                                v-if="playlist.cover_url"
                                :src="playlist.cover_url"
                                :alt="playlist.name"
                                class="w-full h-full object-cover"
                            />
                        </div>
                        <p class="text-white font-medium truncate">{{ playlist.name }}</p>
                        <p class="text-surface-400 text-sm truncate">{{ playlist.songs_count ?? 0 }} songs</p>
                    </RouterLink>
                </div>
            </section>
        </div>

        <div v-else class="text-center py-12">
            <p class="text-surface-400">Search for your favorite music</p>
        </div>
    </div>
</template>

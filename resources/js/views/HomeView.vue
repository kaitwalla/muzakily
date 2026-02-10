<script setup lang="ts">
import { onMounted, ref, computed } from 'vue';
import { RouterLink } from 'vue-router';
import { useAuthStore } from '@/stores/auth';
import { usePlaylistsStore } from '@/stores/playlists';
import * as songsApi from '@/api/songs';
import type { Song } from '@/types/models';

const authStore = useAuthStore();
const playlistsStore = usePlaylistsStore();

const recentlyPlayed = ref<Song[]>([]);
const recentlyPlayedLoading = ref(false);

const displayPlaylists = computed(() => playlistsStore.playlists.slice(0, 5));

async function loadRecentlyPlayed(): Promise<void> {
    recentlyPlayedLoading.value = true;
    try {
        recentlyPlayed.value = await songsApi.getRecentlyPlayed();
    } catch {
        // Silently fail - section will just be empty
    } finally {
        recentlyPlayedLoading.value = false;
    }
}

onMounted(async () => {
    // Load playlists if not already loaded
    if (!playlistsStore.hasPlaylists && !playlistsStore.loading) {
        playlistsStore.fetchPlaylists();
    }

    loadRecentlyPlayed();
});
</script>

<template>
    <div>
        <h1 class="text-3xl font-bold text-white mb-6">
            Welcome back, {{ authStore.user?.name }}
        </h1>

        <section v-if="recentlyPlayed.length > 0 || recentlyPlayedLoading" class="mb-8">
            <h2 class="text-xl font-semibold text-white mb-4">Recently Played</h2>
            <div class="grid grid-cols-2 md:grid-cols-3 lg:grid-cols-4 xl:grid-cols-5 gap-4">
                <!-- Loading skeleton -->
                <template v-if="recentlyPlayedLoading && recentlyPlayed.length === 0">
                    <div
                        v-for="i in 5"
                        :key="i"
                        class="bg-gray-800 rounded-lg p-4 animate-pulse"
                    >
                        <div class="aspect-square bg-gray-700 rounded-lg mb-3" />
                        <div class="h-4 bg-gray-700 rounded w-3/4 mb-2" />
                        <div class="h-3 bg-gray-700 rounded w-1/2" />
                    </div>
                </template>
                <!-- Actual content -->
                <RouterLink
                    v-else
                    v-for="song in recentlyPlayed"
                    :key="song.id"
                    :to="{ name: 'album', params: { id: song.album?.slug ?? song.album_id } }"
                    class="bg-gray-800 rounded-lg p-4 hover:bg-gray-700 transition-colors cursor-pointer"
                >
                    <div class="aspect-square bg-gray-700 rounded-lg mb-3 overflow-hidden">
                        <img
                            v-if="song.album?.cover_url"
                            :src="song.album.cover_url"
                            :alt="song.title"
                            class="w-full h-full object-cover"
                        />
                    </div>
                    <p class="text-white font-medium truncate">{{ song.title }}</p>
                    <p class="text-gray-400 text-sm truncate">{{ song.artist?.name ?? 'Unknown Artist' }}</p>
                </RouterLink>
            </div>
        </section>

        <section v-if="displayPlaylists.length > 0 || playlistsStore.loading" class="mb-8">
            <h2 class="text-xl font-semibold text-white mb-4">Your Playlists</h2>
            <div class="grid grid-cols-2 md:grid-cols-3 lg:grid-cols-4 xl:grid-cols-5 gap-4">
                <!-- Loading skeleton -->
                <template v-if="playlistsStore.loading && displayPlaylists.length === 0">
                    <div
                        v-for="i in 5"
                        :key="i"
                        class="bg-gray-800 rounded-lg p-4 animate-pulse"
                    >
                        <div class="aspect-square bg-gray-700 rounded-lg mb-3" />
                        <div class="h-4 bg-gray-700 rounded w-3/4 mb-2" />
                        <div class="h-3 bg-gray-700 rounded w-1/2" />
                    </div>
                </template>
                <!-- Actual content -->
                <RouterLink
                    v-else
                    v-for="playlist in displayPlaylists"
                    :key="playlist.id"
                    :to="{ name: 'playlist', params: { id: playlist.slug } }"
                    class="bg-gray-800 rounded-lg p-4 hover:bg-gray-700 transition-colors cursor-pointer"
                >
                    <div class="aspect-square bg-gray-700 rounded-lg mb-3 overflow-hidden">
                        <img
                            v-if="playlist.cover_url"
                            :src="playlist.cover_url"
                            :alt="playlist.name"
                            class="w-full h-full object-cover"
                        />
                    </div>
                    <p class="text-white font-medium truncate">{{ playlist.name }}</p>
                    <p class="text-gray-400 text-sm truncate">{{ playlist.songs_count ?? 0 }} songs</p>
                </RouterLink>
            </div>
        </section>

        <!-- Empty state when no content -->
        <div v-if="!recentlyPlayedLoading && recentlyPlayed.length === 0 && !playlistsStore.loading && displayPlaylists.length === 0" class="text-center py-12">
            <p class="text-gray-400">Start playing some music to see your activity here.</p>
        </div>
    </div>
</template>

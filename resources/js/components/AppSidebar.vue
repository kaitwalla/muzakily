<script setup lang="ts">
import { onMounted } from 'vue';
import { RouterLink } from 'vue-router';
import { usePlaylistsStore } from '@/stores/playlists';

const playlistsStore = usePlaylistsStore();

onMounted(() => {
    if (!playlistsStore.hasPlaylists) {
        playlistsStore.fetchPlaylists();
    }
});
</script>

<template>
    <aside class="w-64 bg-gray-800 border-r border-gray-700 p-4 flex flex-col">
        <nav class="space-y-1">
            <RouterLink
                to="/"
                class="flex items-center gap-3 px-4 py-2 text-gray-300 hover:text-white hover:bg-gray-700 rounded-lg transition-colors"
                active-class="bg-gray-700 text-white"
            >
                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 12l2-2m0 0l7-7 7 7M5 10v10a1 1 0 001 1h3m10-11l2 2m-2-2v10a1 1 0 01-1 1h-3m-6 0a1 1 0 001-1v-4a1 1 0 011-1h2a1 1 0 011 1v4a1 1 0 001 1m-6 0h6" />
                </svg>
                <span>Home</span>
            </RouterLink>

            <RouterLink
                to="/search"
                class="flex items-center gap-3 px-4 py-2 text-gray-300 hover:text-white hover:bg-gray-700 rounded-lg transition-colors"
                active-class="bg-gray-700 text-white"
            >
                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z" />
                </svg>
                <span>Search</span>
            </RouterLink>
        </nav>

        <div class="mt-6">
            <h3 class="px-4 text-xs font-semibold text-gray-500 uppercase tracking-wider mb-2">
                Library
            </h3>
            <nav class="space-y-1">
                <RouterLink
                    to="/songs"
                    class="flex items-center gap-3 px-4 py-2 text-gray-300 hover:text-white hover:bg-gray-700 rounded-lg transition-colors"
                    active-class="bg-gray-700 text-white"
                >
                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 19V6l12-3v13M9 19c0 1.105-1.343 2-3 2s-3-.895-3-2 1.343-2 3-2 3 .895 3 2zm12-3c0 1.105-1.343 2-3 2s-3-.895-3-2 1.343-2 3-2 3 .895 3 2zM9 10l12-3" />
                    </svg>
                    <span>Songs</span>
                </RouterLink>

                <RouterLink
                    to="/albums"
                    class="flex items-center gap-3 px-4 py-2 text-gray-300 hover:text-white hover:bg-gray-700 rounded-lg transition-colors"
                    active-class="bg-gray-700 text-white"
                >
                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 11H5m14 0a2 2 0 012 2v6a2 2 0 01-2 2H5a2 2 0 01-2-2v-6a2 2 0 012-2m14 0V9a2 2 0 00-2-2M5 11V9a2 2 0 012-2m0 0V5a2 2 0 012-2h6a2 2 0 012 2v2M7 7h10" />
                    </svg>
                    <span>Albums</span>
                </RouterLink>

                <RouterLink
                    to="/artists"
                    class="flex items-center gap-3 px-4 py-2 text-gray-300 hover:text-white hover:bg-gray-700 rounded-lg transition-colors"
                    active-class="bg-gray-700 text-white"
                >
                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 20h5v-2a3 3 0 00-5.356-1.857M17 20H7m10 0v-2c0-.656-.126-1.283-.356-1.857M7 20H2v-2a3 3 0 015.356-1.857M7 20v-2c0-.656.126-1.283.356-1.857m0 0a5.002 5.002 0 019.288 0M15 7a3 3 0 11-6 0 3 3 0 016 0zm6 3a2 2 0 11-4 0 2 2 0 014 0zM7 10a2 2 0 11-4 0 2 2 0 014 0z" />
                    </svg>
                    <span>Artists</span>
                </RouterLink>

                <RouterLink
                    to="/playlists"
                    class="flex items-center gap-3 px-4 py-2 text-gray-300 hover:text-white hover:bg-gray-700 rounded-lg transition-colors"
                    active-class="bg-gray-700 text-white"
                >
                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 6h16M4 10h16M4 14h16M4 18h16" />
                    </svg>
                    <span>Playlists</span>
                </RouterLink>
            </nav>
        </div>

        <div class="mt-6 flex-1 overflow-hidden flex flex-col">
            <h3 class="px-4 text-xs font-semibold text-gray-500 uppercase tracking-wider mb-2">
                Your Playlists
            </h3>
            <div class="flex-1 overflow-y-auto space-y-1">
                <RouterLink
                    v-for="playlist in playlistsStore.playlists.slice(0, 10)"
                    :key="playlist.id"
                    :to="{ name: 'playlist-detail', params: { slug: playlist.slug } }"
                    class="block px-4 py-2 text-sm text-gray-400 hover:text-white truncate rounded-lg hover:bg-gray-700 transition-colors"
                >
                    {{ playlist.name }}
                </RouterLink>
                <p v-if="!playlistsStore.hasPlaylists && !playlistsStore.loading" class="px-4 py-2 text-sm text-gray-500">
                    No playlists yet
                </p>
            </div>
        </div>
    </aside>
</template>

<script setup lang="ts">
import { RouterLink } from 'vue-router';
</script>

<template>
    <aside class="w-64 bg-gray-800 border-r border-gray-700 p-4">
        <nav class="space-y-2">
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

            <RouterLink
                to="/library"
                class="flex items-center gap-3 px-4 py-2 text-gray-300 hover:text-white hover:bg-gray-700 rounded-lg transition-colors"
                active-class="bg-gray-700 text-white"
            >
                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 11H5m14 0a2 2 0 012 2v6a2 2 0 01-2 2H5a2 2 0 01-2-2v-6a2 2 0 012-2m14 0V9a2 2 0 00-2-2M5 11V9a2 2 0 012-2m0 0V5a2 2 0 012-2h6a2 2 0 012 2v2M7 7h10" />
                </svg>
                <span>Library</span>
            </RouterLink>
        </nav>

        <div class="mt-8">
            <h3 class="px-4 text-xs font-semibold text-gray-500 uppercase tracking-wider">
                Playlists
            </h3>
            <div class="mt-2 space-y-1">
                <p class="px-4 py-2 text-sm text-gray-500">
                    No playlists yet
                </p>
            </div>
        </div>
    </aside>
</template>

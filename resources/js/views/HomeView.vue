<script setup lang="ts">
import { useAuthStore } from '@/stores/auth';

const authStore = useAuthStore();
</script>

<template>
    <div>
        <h1 class="text-3xl font-bold text-white mb-6">
            Welcome back, {{ authStore.user?.name }}
        </h1>

        <section class="mb-8">
            <h2 class="text-xl font-semibold text-white mb-4">Recently Played</h2>
            <div class="grid grid-cols-2 md:grid-cols-3 lg:grid-cols-4 xl:grid-cols-5 gap-4">
                <div
                    v-for="i in 5"
                    :key="i"
                    class="bg-gray-800 rounded-lg p-4 hover:bg-gray-700 transition-colors cursor-pointer"
                >
                    <div class="aspect-square bg-gray-700 rounded-lg mb-3" />
                    <p class="text-white font-medium truncate">Placeholder {{ i }}</p>
                    <p class="text-gray-400 text-sm truncate">Artist name</p>
                </div>
            </div>
        </section>

        <section class="mb-8">
            <h2 class="text-xl font-semibold text-white mb-4">Your Playlists</h2>
            <div class="grid grid-cols-2 md:grid-cols-3 lg:grid-cols-4 xl:grid-cols-5 gap-4">
                <div
                    v-for="i in 5"
                    :key="i"
                    class="bg-gray-800 rounded-lg p-4 hover:bg-gray-700 transition-colors cursor-pointer"
                >
                    <div class="aspect-square bg-gray-700 rounded-lg mb-3" />
                    <p class="text-white font-medium truncate">Playlist {{ i }}</p>
                    <p class="text-gray-400 text-sm truncate">12 songs</p>
                </div>
            </div>
        </section>
    </div>
</template>

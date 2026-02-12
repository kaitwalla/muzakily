<script setup lang="ts">
import { onMounted } from 'vue';
import { RouterLink } from 'vue-router';
import { useArtistsStore } from '@/stores/artists';

const artistsStore = useArtistsStore();

onMounted(() => {
    artistsStore.fetchArtists();
});
</script>

<template>
    <div>
        <h1 class="text-3xl font-bold text-white mb-6">Artists</h1>

        <div v-if="artistsStore.loading && !artistsStore.hasArtists" class="text-center py-12">
            <p class="text-gray-400">Loading artists...</p>
        </div>

        <div v-else-if="artistsStore.error" class="text-center py-12">
            <p class="text-red-400">{{ artistsStore.error }}</p>
        </div>

        <div v-else-if="!artistsStore.hasArtists" class="text-center py-12">
            <p class="text-gray-400">No artists found</p>
        </div>

        <div v-else class="grid grid-cols-2 md:grid-cols-3 lg:grid-cols-4 xl:grid-cols-5 gap-4">
            <RouterLink
                v-for="artist in artistsStore.artists"
                :key="artist.id"
                :to="{ name: 'artist-detail', params: { slug: artist.id } }"
                class="bg-gray-800 rounded-lg p-4 hover:bg-gray-700 transition-colors group text-center"
            >
                <div class="aspect-square bg-gray-700 rounded-full mb-3 mx-auto overflow-hidden w-32 h-32">
                    <img
                        v-if="artist.image"
                        :src="artist.image"
                        :alt="artist.name"
                        class="w-full h-full object-cover group-hover:scale-105 transition-transform"
                    />
                    <div v-else class="w-full h-full flex items-center justify-center">
                        <svg class="w-16 h-16 text-gray-600" fill="currentColor" viewBox="0 0 24 24">
                            <path d="M12 12c2.21 0 4-1.79 4-4s-1.79-4-4-4-4 1.79-4 4 1.79 4 4 4zm0 2c-2.67 0-8 1.34-8 4v2h16v-2c0-2.66-5.33-4-8-4z"/>
                        </svg>
                    </div>
                </div>
                <p class="text-white font-medium truncate">{{ artist.name }}</p>
                <p class="text-gray-500 text-sm">Artist</p>
            </RouterLink>
        </div>

        <div v-if="artistsStore.hasMore" class="mt-6 text-center">
            <button
                @click="artistsStore.loadMore"
                :disabled="artistsStore.loading"
                class="px-6 py-2 bg-gray-700 hover:bg-gray-600 text-white rounded-full transition-colors disabled:opacity-50"
            >
                {{ artistsStore.loading ? 'Loading...' : 'Load More' }}
            </button>
        </div>
    </div>
</template>

<script setup lang="ts">
import { onMounted } from 'vue';
import { RouterLink } from 'vue-router';
import { useAlbumsStore } from '@/stores/albums';

const albumsStore = useAlbumsStore();

onMounted(() => {
    albumsStore.fetchAlbums();
});
</script>

<template>
    <div>
        <h1 class="text-3xl font-bold text-white mb-6">Albums</h1>

        <div v-if="albumsStore.loading && !albumsStore.hasAlbums" class="text-center py-12">
            <p class="text-gray-400">Loading albums...</p>
        </div>

        <div v-else-if="albumsStore.error" class="text-center py-12">
            <p class="text-red-400">{{ albumsStore.error }}</p>
        </div>

        <div v-else-if="!albumsStore.hasAlbums" class="text-center py-12">
            <p class="text-gray-400">No albums found</p>
        </div>

        <div v-else class="grid grid-cols-2 md:grid-cols-3 lg:grid-cols-4 xl:grid-cols-5 gap-4">
            <RouterLink
                v-for="album in albumsStore.albums"
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
                    <div v-else class="w-full h-full flex items-center justify-center">
                        <svg class="w-16 h-16 text-gray-600" fill="currentColor" viewBox="0 0 24 24">
                            <path d="M12 2C6.48 2 2 6.48 2 12s4.48 10 10 10 10-4.48 10-10S17.52 2 12 2zm0 14.5c-2.49 0-4.5-2.01-4.5-4.5S9.51 7.5 12 7.5s4.5 2.01 4.5 4.5-2.01 4.5-4.5 4.5zm0-5.5c-.55 0-1 .45-1 1s.45 1 1 1 1-.45 1-1-.45-1-1-1z"/>
                        </svg>
                    </div>
                </div>
                <p class="text-white font-medium truncate">{{ album.title }}</p>
                <p class="text-gray-400 text-sm truncate">{{ album.artist?.name ?? 'Unknown Artist' }}</p>
                <p v-if="album.release_date" class="text-gray-500 text-xs mt-1">
                    {{ new Date(album.release_date).getFullYear() }}
                </p>
            </RouterLink>
        </div>

        <div v-if="albumsStore.hasMore" class="mt-6 text-center">
            <button
                @click="albumsStore.loadMore"
                :disabled="albumsStore.loading"
                class="px-6 py-2 bg-gray-700 hover:bg-gray-600 text-white rounded-full transition-colors disabled:opacity-50"
            >
                {{ albumsStore.loading ? 'Loading...' : 'Load More' }}
            </button>
        </div>
    </div>
</template>

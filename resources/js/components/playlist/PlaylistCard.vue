<script setup lang="ts">
import { computed } from 'vue';
import { RouterLink } from 'vue-router';
import { usePlayerStore } from '@/stores/player';
import type { Playlist } from '@/types/models';

interface Props {
    playlist: Playlist;
}

const props = defineProps<Props>();

const playerStore = usePlayerStore();

const songCount = computed(() => {
    return props.playlist.songs_count ?? props.playlist.songs?.length ?? 0;
});

function handlePlay(event: Event): void {
    event.preventDefault();
    event.stopPropagation();

    if (props.playlist.songs && props.playlist.songs.length > 0) {
        playerStore.play(props.playlist.songs, 0);
    }
}
</script>

<template>
    <RouterLink
        :to="{ name: 'playlist-detail', params: { slug: playlist.id } }"
        class="block bg-gray-800/50 hover:bg-gray-700/50 rounded-lg p-4 transition-colors group"
    >
        <!-- Cover image -->
        <div class="relative aspect-square mb-4 rounded-md overflow-hidden bg-gray-700 shadow-lg">
            <img
                v-if="playlist.cover_url"
                :src="playlist.cover_url"
                :alt="playlist.name"
                class="w-full h-full object-cover"
            />
            <div v-else class="w-full h-full flex items-center justify-center">
                <svg class="w-12 h-12 text-gray-600" fill="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                    <path d="M15 6H3v2h12V6zm0 4H3v2h12v-2zM3 16h8v-2H3v2zM17 6v8.18c-.31-.11-.65-.18-1-.18-1.66 0-3 1.34-3 3s1.34 3 3 3 3-1.34 3-3V8h3V6h-5z"/>
                </svg>
            </div>

            <!-- Play button overlay -->
            <button
                @click="handlePlay"
                class="absolute bottom-2 right-2 w-12 h-12 bg-green-500 rounded-full flex items-center justify-center shadow-lg opacity-0 translate-y-2 group-hover:opacity-100 group-hover:translate-y-0 transition-all hover:scale-105 hover:bg-green-400"
                :aria-label="`Play ${playlist.name}`"
            >
                <svg class="w-5 h-5 text-black ml-0.5" fill="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                    <path d="M8 5v14l11-7z"/>
                </svg>
            </button>
        </div>

        <!-- Playlist info -->
        <h3 class="font-semibold text-white truncate">{{ playlist.name }}</h3>
        <p class="text-sm text-gray-400 mt-1 line-clamp-2">
            {{ playlist.description || `${songCount} song${songCount === 1 ? '' : 's'}` }}
        </p>
    </RouterLink>
</template>

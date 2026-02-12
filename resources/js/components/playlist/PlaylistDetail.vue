<script setup lang="ts">
import { computed } from 'vue';
import { RouterLink } from 'vue-router';
import { usePlayerStore } from '@/stores/player';
import type { Playlist, Song } from '@/types/models';

interface Props {
    playlist: Playlist;
    songs: Song[];
    loading?: boolean;
}

const props = withDefaults(defineProps<Props>(), {
    loading: false,
});

const emit = defineEmits<{
    play: [];
    'play-song': [song: Song, index: number];
    edit: [];
    delete: [];
    'remove-song': [song: Song];
}>();

const playerStore = usePlayerStore();

const totalDuration = computed(() => {
    const total = props.songs.reduce((sum, song) => sum + song.length, 0);
    const hours = Math.floor(total / 3600);
    const mins = Math.floor((total % 3600) / 60);
    if (hours > 0) {
        return `${hours} hr ${mins} min`;
    }
    return `${mins} min`;
});

function formatDuration(seconds: number): string {
    const mins = Math.floor(seconds / 60);
    const secs = Math.floor(seconds % 60);
    return `${mins}:${secs.toString().padStart(2, '0')}`;
}

function handlePlayPlaylist(): void {
    emit('play');
}

function handlePlaySong(song: Song, index: number): void {
    emit('play-song', song, index);
}
</script>

<template>
    <div>
        <!-- Playlist Header -->
        <div class="flex gap-6 mb-8">
            <div class="w-56 h-56 bg-gray-700 rounded-lg overflow-hidden flex-shrink-0 shadow-xl">
                <img
                    v-if="playlist.cover_url"
                    :src="playlist.cover_url"
                    :alt="playlist.name"
                    class="w-full h-full object-cover"
                />
                <div v-else class="w-full h-full flex items-center justify-center">
                    <svg class="w-24 h-24 text-gray-600" fill="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                        <path d="M15 6H3v2h12V6zm0 4H3v2h12v-2zM3 16h8v-2H3v2zM17 6v8.18c-.31-.11-.65-.18-1-.18-1.66 0-3 1.34-3 3s1.34 3 3 3 3-1.34 3-3V8h3V6h-5z"/>
                    </svg>
                </div>
            </div>
            <div class="flex flex-col justify-end">
                <p class="text-sm text-gray-400 uppercase font-medium">Playlist</p>
                <h1 class="text-5xl font-bold text-white mt-2 mb-2">
                    {{ playlist.name }}
                </h1>
                <p v-if="playlist.description" class="text-gray-300 mb-4 max-w-2xl">
                    {{ playlist.description }}
                </p>
                <div class="flex items-center gap-2 text-gray-400">
                    <span>{{ songs.length }} song{{ songs.length === 1 ? '' : 's' }}</span>
                    <span>&bull;</span>
                    <span>{{ totalDuration }}</span>
                </div>
            </div>
        </div>

        <!-- Actions -->
        <div class="flex items-center gap-4 mb-6">
            <button
                @click="handlePlayPlaylist"
                :disabled="songs.length === 0"
                class="w-14 h-14 bg-green-500 hover:bg-green-400 hover:scale-105 rounded-full flex items-center justify-center transition-all disabled:opacity-50 disabled:hover:scale-100"
                aria-label="Play playlist"
            >
                <svg class="w-6 h-6 text-black ml-1" fill="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                    <path d="M8 5v14l11-7z"/>
                </svg>
            </button>
            <button
                @click="emit('edit')"
                class="p-3 text-gray-400 hover:text-white transition-colors"
                title="Edit playlist"
            >
                <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z"/>
                </svg>
            </button>
            <button
                @click="emit('delete')"
                class="p-3 text-gray-400 hover:text-red-400 transition-colors"
                title="Delete playlist"
            >
                <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"/>
                </svg>
            </button>
        </div>

        <!-- Loading state -->
        <div v-if="loading" class="text-center py-12 bg-gray-800/50 rounded-lg">
            <svg class="w-8 h-8 text-gray-400 animate-spin mx-auto" fill="none" viewBox="0 0 24 24" aria-hidden="true">
                <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4" />
                <path
                    class="opacity-75"
                    fill="currentColor"
                    d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"
                />
            </svg>
        </div>

        <!-- Songs List -->
        <div v-else-if="songs.length > 0" class="bg-gray-800/50 rounded-lg overflow-hidden">
            <table class="w-full">
                <thead class="border-b border-gray-700">
                    <tr class="text-left text-sm text-gray-400">
                        <th class="px-4 py-3 w-12">#</th>
                        <th class="px-4 py-3">Title</th>
                        <th class="px-4 py-3">Album</th>
                        <th class="px-4 py-3 text-right">Duration</th>
                        <th class="px-4 py-3 w-12"></th>
                    </tr>
                </thead>
                <tbody>
                    <tr
                        v-for="(song, index) in songs"
                        :key="song.id"
                        @click="handlePlaySong(song, index)"
                        class="hover:bg-gray-700/50 cursor-pointer transition-colors group"
                        :class="{ 'bg-gray-700/50': playerStore.currentSong?.id === song.id }"
                    >
                        <td class="px-4 py-3 text-gray-400">
                            <span v-if="playerStore.currentSong?.id === song.id && playerStore.isPlaying" class="text-green-500">
                                <svg class="w-4 h-4 animate-pulse" fill="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                                    <path d="M8 5v14l11-7z"/>
                                </svg>
                            </span>
                            <span v-else>{{ index + 1 }}</span>
                        </td>
                        <td class="px-4 py-3">
                            <p class="text-white font-medium" :class="{ 'text-green-500': playerStore.currentSong?.id === song.id }">
                                {{ song.title }}
                            </p>
                            <RouterLink
                                v-if="song.artist_slug"
                                :to="{ name: 'artist-detail', params: { slug: song.artist_slug } }"
                                class="text-gray-400 text-sm hover:text-white hover:underline"
                                @click.stop
                            >
                                {{ song.artist_name }}
                            </RouterLink>
                            <span v-else class="text-gray-400 text-sm">
                                {{ song.artist_name ?? 'Unknown' }}
                            </span>
                        </td>
                        <td class="px-4 py-3">
                            <RouterLink
                                v-if="song.album_slug"
                                :to="{ name: 'album-detail', params: { slug: song.album_slug } }"
                                class="text-gray-400 hover:text-white hover:underline"
                                @click.stop
                            >
                                {{ song.album_name }}
                            </RouterLink>
                            <span v-else class="text-gray-400">{{ song.album_name ?? '-' }}</span>
                        </td>
                        <td class="px-4 py-3 text-gray-400 text-right tabular-nums">
                            {{ formatDuration(song.length) }}
                        </td>
                        <td class="px-4 py-3">
                            <button
                                @click.stop="emit('remove-song', song)"
                                class="p-1 text-gray-500 hover:text-red-400 opacity-0 group-hover:opacity-100 transition-opacity"
                                aria-label="Remove from playlist"
                            >
                                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" />
                                </svg>
                            </button>
                        </td>
                    </tr>
                </tbody>
            </table>
        </div>

        <!-- Empty state -->
        <div v-else class="text-center py-12 bg-gray-800/50 rounded-lg">
            <svg class="w-16 h-16 text-gray-600 mx-auto mb-4" fill="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                <path d="M15 6H3v2h12V6zm0 4H3v2h12v-2zM3 16h8v-2H3v2zM17 6v8.18c-.31-.11-.65-.18-1-.18-1.66 0-3 1.34-3 3s1.34 3 3 3 3-1.34 3-3V8h3V6h-5z"/>
            </svg>
            <p class="text-gray-400">This playlist is empty</p>
            <p class="text-gray-500 text-sm mt-2">Add songs to get started</p>
        </div>
    </div>
</template>

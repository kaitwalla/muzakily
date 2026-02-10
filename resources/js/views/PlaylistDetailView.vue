<script setup lang="ts">
import { onMounted, watch, ref } from 'vue';
import { useRoute, useRouter, RouterLink } from 'vue-router';
import { usePlaylistsStore } from '@/stores/playlists';
import { usePlayerStore } from '@/stores/player';
import type { Song } from '@/types/models';

const route = useRoute();
const router = useRouter();
const playlistsStore = usePlaylistsStore();
const playerStore = usePlayerStore();

const showDeleteConfirm = ref(false);
const isDeleting = ref(false);

async function loadPlaylist(): Promise<void> {
    const slug = route.params.slug as string;
    const playlist = await playlistsStore.fetchPlaylist(slug);
    await playlistsStore.fetchPlaylistSongs(playlist.id);
}

onMounted(() => {
    loadPlaylist();
});

watch(() => route.params.slug, () => {
    loadPlaylist();
});

function playPlaylist(): void {
    if (playlistsStore.currentPlaylistSongs.length > 0) {
        playerStore.play(playlistsStore.currentPlaylistSongs, 0);
    }
}

function playSong(_song: Song, index: number): void {
    playerStore.play(playlistsStore.currentPlaylistSongs, index);
}

async function deletePlaylist(): Promise<void> {
    if (!playlistsStore.currentPlaylist || isDeleting.value) return;

    isDeleting.value = true;
    try {
        await playlistsStore.deletePlaylist(playlistsStore.currentPlaylist.id);
        router.push({ name: 'playlists' });
    } catch {
        // Error handled by store
    } finally {
        isDeleting.value = false;
        showDeleteConfirm.value = false;
    }
}

function formatDuration(seconds: number): string {
    const mins = Math.floor(seconds / 60);
    const secs = Math.floor(seconds % 60);
    return `${mins}:${secs.toString().padStart(2, '0')}`;
}

function getTotalDuration(): string {
    const total = playlistsStore.currentPlaylistSongs.reduce((sum, song) => sum + song.duration, 0);
    const hours = Math.floor(total / 3600);
    const mins = Math.floor((total % 3600) / 60);
    if (hours > 0) {
        return `${hours} hr ${mins} min`;
    }
    return `${mins} min`;
}
</script>

<template>
    <div>
        <div v-if="playlistsStore.loading && !playlistsStore.currentPlaylist" class="text-center py-12">
            <p class="text-gray-400">Loading playlist...</p>
        </div>

        <div v-else-if="playlistsStore.error" class="text-center py-12">
            <p class="text-red-400">{{ playlistsStore.error }}</p>
        </div>

        <template v-else-if="playlistsStore.currentPlaylist">
            <!-- Playlist Header -->
            <div class="flex gap-6 mb-8">
                <div class="w-56 h-56 bg-gray-700 rounded-lg overflow-hidden flex-shrink-0 shadow-xl">
                    <img
                        v-if="playlistsStore.currentPlaylist.cover_url"
                        :src="playlistsStore.currentPlaylist.cover_url"
                        :alt="playlistsStore.currentPlaylist.name"
                        class="w-full h-full object-cover"
                    />
                    <div v-else class="w-full h-full flex items-center justify-center">
                        <svg class="w-24 h-24 text-gray-600" fill="currentColor" viewBox="0 0 24 24">
                            <path d="M15 6H3v2h12V6zm0 4H3v2h12v-2zM3 16h8v-2H3v2zM17 6v8.18c-.31-.11-.65-.18-1-.18-1.66 0-3 1.34-3 3s1.34 3 3 3 3-1.34 3-3V8h3V6h-5z"/>
                        </svg>
                    </div>
                </div>
                <div class="flex flex-col justify-end">
                    <p class="text-sm text-gray-400 uppercase font-medium">Playlist</p>
                    <h1 class="text-5xl font-bold text-white mt-2 mb-2">
                        {{ playlistsStore.currentPlaylist.name }}
                    </h1>
                    <p v-if="playlistsStore.currentPlaylist.description" class="text-gray-300 mb-4 max-w-2xl">
                        {{ playlistsStore.currentPlaylist.description }}
                    </p>
                    <div class="flex items-center gap-2 text-gray-400">
                        <span>{{ playlistsStore.currentPlaylistSongs.length }} songs</span>
                        <span>&bull;</span>
                        <span>{{ getTotalDuration() }}</span>
                    </div>
                </div>
            </div>

            <!-- Actions -->
            <div class="flex items-center gap-4 mb-6">
                <button
                    @click="playPlaylist"
                    :disabled="playlistsStore.currentPlaylistSongs.length === 0"
                    class="w-14 h-14 bg-green-500 hover:bg-green-400 hover:scale-105 rounded-full flex items-center justify-center transition-all disabled:opacity-50"
                >
                    <svg class="w-6 h-6 text-black ml-1" fill="currentColor" viewBox="0 0 24 24">
                        <path d="M8 5v14l11-7z"/>
                    </svg>
                </button>
                <button
                    @click="showDeleteConfirm = true"
                    class="p-3 text-gray-400 hover:text-white transition-colors"
                    title="Delete playlist"
                >
                    <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"/>
                    </svg>
                </button>
            </div>

            <!-- Songs List -->
            <div v-if="playlistsStore.currentPlaylistSongs.length > 0" class="bg-gray-800/50 rounded-lg overflow-hidden">
                <table class="w-full">
                    <thead class="border-b border-gray-700">
                        <tr class="text-left text-sm text-gray-400">
                            <th class="px-4 py-3 w-12">#</th>
                            <th class="px-4 py-3">Title</th>
                            <th class="px-4 py-3">Album</th>
                            <th class="px-4 py-3 text-right">Duration</th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr
                            v-for="(song, index) in playlistsStore.currentPlaylistSongs"
                            :key="song.id"
                            @click="playSong(song, index)"
                            class="hover:bg-gray-700/50 cursor-pointer transition-colors group"
                            :class="{ 'bg-gray-700/50': playerStore.currentSong?.id === song.id }"
                        >
                            <td class="px-4 py-3 text-gray-400">
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
                                <RouterLink
                                    v-if="song.artist"
                                    :to="{ name: 'artist-detail', params: { slug: song.artist.slug } }"
                                    class="text-gray-400 text-sm hover:underline"
                                    @click.stop
                                >
                                    {{ song.artist.name }}
                                </RouterLink>
                            </td>
                            <td class="px-4 py-3">
                                <RouterLink
                                    v-if="song.album"
                                    :to="{ name: 'album-detail', params: { slug: song.album.slug } }"
                                    class="text-gray-400 hover:underline"
                                    @click.stop
                                >
                                    {{ song.album.title }}
                                </RouterLink>
                                <span v-else class="text-gray-500">-</span>
                            </td>
                            <td class="px-4 py-3 text-gray-400 text-right">
                                {{ formatDuration(song.duration) }}
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>

            <div v-else class="text-center py-12 bg-gray-800/50 rounded-lg">
                <p class="text-gray-400">This playlist is empty</p>
                <p class="text-gray-500 text-sm mt-2">Add songs to get started</p>
            </div>
        </template>

        <!-- Delete Confirmation Modal -->
        <Teleport to="body">
            <div
                v-if="showDeleteConfirm"
                class="fixed inset-0 bg-black/50 flex items-center justify-center z-50"
                @click.self="showDeleteConfirm = false"
            >
                <div class="bg-gray-800 rounded-lg p-6 w-full max-w-sm">
                    <h2 class="text-xl font-bold text-white mb-2">Delete Playlist?</h2>
                    <p class="text-gray-400 mb-6">
                        This will permanently delete "{{ playlistsStore.currentPlaylist?.name }}".
                    </p>
                    <div class="flex gap-3">
                        <button
                            @click="showDeleteConfirm = false"
                            class="flex-1 px-4 py-2 bg-gray-700 hover:bg-gray-600 text-white rounded-lg transition-colors"
                        >
                            Cancel
                        </button>
                        <button
                            @click="deletePlaylist"
                            :disabled="isDeleting"
                            class="flex-1 px-4 py-2 bg-red-500 hover:bg-red-600 disabled:bg-red-500/50 text-white font-semibold rounded-lg transition-colors"
                        >
                            {{ isDeleting ? 'Deleting...' : 'Delete' }}
                        </button>
                    </div>
                </div>
            </div>
        </Teleport>
    </div>
</template>

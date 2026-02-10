<script setup lang="ts">
import { ref, onMounted } from 'vue';
import { usePlaylistsStore } from '@/stores/playlists';
import type { Song, Playlist } from '@/types/models';

interface Props {
    songs: Song[];
}

const props = defineProps<Props>();

const emit = defineEmits<{
    close: [];
    added: [playlist: Playlist];
}>();

const playlistsStore = usePlaylistsStore();

const loading = ref(false);
const error = ref<string | null>(null);
const showCreateForm = ref(false);
const newPlaylistName = ref('');
const creating = ref(false);

onMounted(async () => {
    if (!playlistsStore.hasPlaylists) {
        loading.value = true;
        try {
            await playlistsStore.fetchPlaylists();
        } catch {
            error.value = 'Failed to load playlists';
        } finally {
            loading.value = false;
        }
    }
});

async function addToPlaylist(playlist: Playlist): Promise<void> {
    loading.value = true;
    error.value = null;

    try {
        const songIds = props.songs.map((s) => s.id);
        await playlistsStore.addSongsToPlaylist(playlist.id, songIds);
        emit('added', playlist);
        emit('close');
    } catch {
        error.value = 'Failed to add songs to playlist';
    } finally {
        loading.value = false;
    }
}

async function createAndAdd(): Promise<void> {
    if (!newPlaylistName.value.trim()) return;

    creating.value = true;
    error.value = null;

    try {
        const playlist = await playlistsStore.createPlaylist({
            name: newPlaylistName.value.trim(),
        });

        const songIds = props.songs.map((s) => s.id);
        await playlistsStore.addSongsToPlaylist(playlist.id, songIds);

        emit('added', playlist);
        emit('close');
    } catch {
        error.value = 'Failed to create playlist';
    } finally {
        creating.value = false;
    }
}
</script>

<template>
    <div
        class="fixed inset-0 bg-black/50 flex items-center justify-center z-50"
        @click.self="emit('close')"
    >
        <div class="bg-gray-800 rounded-lg w-full max-w-md max-h-[80vh] flex flex-col">
            <!-- Header -->
            <div class="flex items-center justify-between px-6 py-4 border-b border-gray-700">
                <h2 class="text-xl font-bold text-white">
                    Add to playlist
                </h2>
                <button
                    @click="emit('close')"
                    class="p-1 text-gray-400 hover:text-white transition-colors"
                    aria-label="Close"
                >
                    <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" />
                    </svg>
                </button>
            </div>

            <!-- Error message -->
            <div v-if="error" class="px-6 py-3 bg-red-500/10 border-b border-red-500/20">
                <p class="text-sm text-red-400">{{ error }}</p>
            </div>

            <!-- Song count info -->
            <div class="px-6 py-3 border-b border-gray-700">
                <p class="text-sm text-gray-400">
                    {{ songs.length }} song{{ songs.length === 1 ? '' : 's' }} selected
                </p>
            </div>

            <!-- Create new playlist -->
            <div class="px-6 py-3 border-b border-gray-700">
                <button
                    v-if="!showCreateForm"
                    @click="showCreateForm = true"
                    class="flex items-center gap-3 w-full py-2 text-left hover:bg-gray-700/50 rounded-lg px-3 -mx-3 transition-colors"
                >
                    <div class="w-12 h-12 bg-gray-700 rounded flex items-center justify-center">
                        <svg class="w-6 h-6 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4" />
                        </svg>
                    </div>
                    <span class="font-medium text-white">Create new playlist</span>
                </button>

                <form v-else @submit.prevent="createAndAdd" class="flex gap-2">
                    <input
                        v-model="newPlaylistName"
                        type="text"
                        placeholder="Playlist name"
                        class="flex-1 bg-gray-700 border border-gray-600 rounded-lg px-4 py-2 text-white placeholder-gray-400 focus:outline-none focus:border-green-500"
                        :disabled="creating"
                    />
                    <button
                        type="submit"
                        :disabled="!newPlaylistName.trim() || creating"
                        class="px-4 py-2 bg-green-500 hover:bg-green-400 disabled:bg-green-500/50 text-black font-semibold rounded-lg transition-colors"
                    >
                        {{ creating ? 'Creating...' : 'Create' }}
                    </button>
                    <button
                        type="button"
                        @click="showCreateForm = false; newPlaylistName = ''"
                        class="px-4 py-2 text-gray-400 hover:text-white transition-colors"
                    >
                        Cancel
                    </button>
                </form>
            </div>

            <!-- Playlist list -->
            <div class="flex-1 overflow-y-auto">
                <div v-if="loading && !playlistsStore.hasPlaylists" class="flex items-center justify-center py-12">
                    <svg class="w-6 h-6 text-gray-400 animate-spin" fill="none" viewBox="0 0 24 24" aria-hidden="true">
                        <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4" />
                        <path
                            class="opacity-75"
                            fill="currentColor"
                            d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"
                        />
                    </svg>
                </div>

                <div v-else-if="playlistsStore.playlists.length === 0" class="text-center py-12">
                    <p class="text-gray-400">No playlists yet</p>
                    <p class="text-gray-500 text-sm mt-1">Create one above to get started</p>
                </div>

                <div v-else>
                    <button
                        v-for="playlist in playlistsStore.playlists"
                        :key="playlist.id"
                        @click="addToPlaylist(playlist)"
                        :disabled="loading"
                        class="flex items-center gap-3 w-full px-6 py-3 hover:bg-gray-700/50 transition-colors disabled:opacity-50"
                    >
                        <div class="w-12 h-12 bg-gray-700 rounded overflow-hidden flex-shrink-0">
                            <img
                                v-if="playlist.cover_url"
                                :src="playlist.cover_url"
                                :alt="playlist.name"
                                class="w-full h-full object-cover"
                            />
                            <div v-else class="w-full h-full flex items-center justify-center">
                                <svg class="w-6 h-6 text-gray-600" fill="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                                    <path d="M15 6H3v2h12V6zm0 4H3v2h12v-2zM3 16h8v-2H3v2zM17 6v8.18c-.31-.11-.65-.18-1-.18-1.66 0-3 1.34-3 3s1.34 3 3 3 3-1.34 3-3V8h3V6h-5z"/>
                                </svg>
                            </div>
                        </div>
                        <div class="flex-1 text-left">
                            <p class="font-medium text-white">{{ playlist.name }}</p>
                            <p class="text-sm text-gray-400">
                                {{ playlist.songs_count ?? 0 }} song{{ (playlist.songs_count ?? 0) === 1 ? '' : 's' }}
                            </p>
                        </div>
                    </button>
                </div>
            </div>
        </div>
    </div>
</template>

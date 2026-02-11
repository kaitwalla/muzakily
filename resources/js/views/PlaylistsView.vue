<script setup lang="ts">
import { onMounted, ref } from 'vue';
import { RouterLink } from 'vue-router';
import { usePlaylistsStore } from '@/stores/playlists';
import SmartPlaylistEditor from '@/components/playlist/SmartPlaylistEditor.vue';
import type { SmartPlaylistRuleGroup } from '@/config/smartPlaylist';

const playlistsStore = usePlaylistsStore();
const showCreateModal = ref(false);
const newPlaylistName = ref('');
const newPlaylistDescription = ref('');
const isSmartPlaylist = ref(false);
const smartPlaylistRules = ref<SmartPlaylistRuleGroup[]>([]);
const isCreating = ref(false);

onMounted(() => {
    playlistsStore.fetchPlaylists();
});

function resetCreateForm(): void {
    newPlaylistName.value = '';
    newPlaylistDescription.value = '';
    isSmartPlaylist.value = false;
    smartPlaylistRules.value = [];
}

function handleRulesUpdate(rules: SmartPlaylistRuleGroup[]): void {
    smartPlaylistRules.value = rules;
}

function handleSmartPlaylistSave(rules: SmartPlaylistRuleGroup[]): void {
    smartPlaylistRules.value = rules;
    createPlaylist();
}

function handleSmartPlaylistCancel(): void {
    showCreateModal.value = false;
    resetCreateForm();
}

async function createPlaylist(): Promise<void> {
    if (!newPlaylistName.value.trim() || isCreating.value) return;

    isCreating.value = true;
    try {
        await playlistsStore.createPlaylist({
            name: newPlaylistName.value.trim(),
            description: newPlaylistDescription.value.trim() || undefined,
            is_smart: isSmartPlaylist.value,
            rules: isSmartPlaylist.value ? smartPlaylistRules.value : undefined,
        });
        showCreateModal.value = false;
        resetCreateForm();
    } catch {
        // Error is handled by store
    } finally {
        isCreating.value = false;
    }
}
</script>

<template>
    <div>
        <div class="flex items-center justify-between mb-6">
            <h1 class="text-3xl font-bold text-white">Playlists</h1>
            <button
                @click="showCreateModal = true"
                class="px-4 py-2 bg-green-500 hover:bg-green-600 text-white font-semibold rounded-full transition-colors"
            >
                New Playlist
            </button>
        </div>

        <div v-if="playlistsStore.loading && !playlistsStore.hasPlaylists" class="text-center py-12">
            <p class="text-gray-400">Loading playlists...</p>
        </div>

        <div v-else-if="playlistsStore.error" class="text-center py-12">
            <p class="text-red-400">{{ playlistsStore.error }}</p>
        </div>

        <div v-else-if="!playlistsStore.hasPlaylists" class="text-center py-12">
            <p class="text-gray-400">No playlists yet</p>
            <button
                @click="showCreateModal = true"
                class="mt-4 px-6 py-2 bg-gray-700 hover:bg-gray-600 text-white rounded-full transition-colors"
            >
                Create your first playlist
            </button>
        </div>

        <div v-else class="grid grid-cols-2 md:grid-cols-3 lg:grid-cols-4 xl:grid-cols-5 gap-4">
            <RouterLink
                v-for="playlist in playlistsStore.playlists"
                :key="playlist.id"
                :to="{ name: 'playlist-detail', params: { slug: playlist.slug } }"
                class="bg-gray-800 rounded-lg p-4 hover:bg-gray-700 transition-colors group"
            >
                <div class="aspect-square bg-gray-700 rounded-lg mb-3 overflow-hidden">
                    <img
                        v-if="playlist.cover_url"
                        :src="playlist.cover_url"
                        :alt="playlist.name"
                        class="w-full h-full object-cover group-hover:scale-105 transition-transform"
                    />
                    <div v-else class="w-full h-full flex items-center justify-center">
                        <svg class="w-16 h-16 text-gray-600" fill="currentColor" viewBox="0 0 24 24">
                            <path d="M15 6H3v2h12V6zm0 4H3v2h12v-2zM3 16h8v-2H3v2zM17 6v8.18c-.31-.11-.65-.18-1-.18-1.66 0-3 1.34-3 3s1.34 3 3 3 3-1.34 3-3V8h3V6h-5z"/>
                        </svg>
                    </div>
                </div>
                <p class="text-white font-medium truncate">{{ playlist.name }}</p>
                <p class="text-gray-400 text-sm truncate">
                    {{ playlist.songs_count ?? 0 }} songs
                </p>
            </RouterLink>
        </div>

        <div v-if="playlistsStore.hasMore" class="mt-6 text-center">
            <button
                @click="playlistsStore.loadMore"
                :disabled="playlistsStore.loading"
                class="px-6 py-2 bg-gray-700 hover:bg-gray-600 text-white rounded-full transition-colors disabled:opacity-50"
            >
                {{ playlistsStore.loading ? 'Loading...' : 'Load More' }}
            </button>
        </div>

        <!-- Create Playlist Modal -->
        <Teleport to="body">
            <div
                v-if="showCreateModal"
                class="fixed inset-0 bg-black/50 flex items-center justify-center z-50 overflow-y-auto py-8"
                @click.self="showCreateModal = false; resetCreateForm()"
            >
                <div
                    class="bg-gray-800 rounded-lg p-6 w-full mx-4 my-auto"
                    :class="isSmartPlaylist ? 'max-w-2xl' : 'max-w-md'"
                >
                    <h2 class="text-xl font-bold text-white mb-4">Create Playlist</h2>
                    <form @submit.prevent="createPlaylist">
                        <div class="mb-4">
                            <label for="playlist-name" class="block text-sm font-medium text-gray-300 mb-2">
                                Name
                            </label>
                            <input
                                id="playlist-name"
                                v-model="newPlaylistName"
                                type="text"
                                required
                                class="w-full px-4 py-2 bg-gray-700 border border-gray-600 rounded-lg text-white placeholder-gray-400 focus:outline-none focus:ring-2 focus:ring-green-500"
                                placeholder="My Playlist"
                            />
                        </div>
                        <div class="mb-4">
                            <label for="playlist-description" class="block text-sm font-medium text-gray-300 mb-2">
                                Description (optional)
                            </label>
                            <textarea
                                id="playlist-description"
                                v-model="newPlaylistDescription"
                                rows="3"
                                class="w-full px-4 py-2 bg-gray-700 border border-gray-600 rounded-lg text-white placeholder-gray-400 focus:outline-none focus:ring-2 focus:ring-green-500 resize-none"
                                placeholder="Add a description..."
                            />
                        </div>

                        <!-- Smart Playlist Toggle -->
                        <div class="mb-6">
                            <label class="flex items-center gap-3 cursor-pointer">
                                <input
                                    v-model="isSmartPlaylist"
                                    type="checkbox"
                                    class="w-5 h-5 rounded bg-gray-700 border-gray-600 text-green-500 focus:ring-green-500 focus:ring-offset-0 cursor-pointer"
                                />
                                <div>
                                    <span class="text-white font-medium">Smart Playlist</span>
                                    <p class="text-sm text-gray-400">Automatically populate based on rules</p>
                                </div>
                            </label>
                        </div>

                        <!-- Smart Playlist Editor -->
                        <div v-if="isSmartPlaylist" class="mb-6">
                            <SmartPlaylistEditor
                                :initial-rules="smartPlaylistRules"
                                @update:rules="handleRulesUpdate"
                                @save="handleSmartPlaylistSave"
                                @cancel="handleSmartPlaylistCancel"
                            />
                        </div>

                        <!-- Regular playlist buttons (only show when not smart) -->
                        <div v-if="!isSmartPlaylist" class="flex gap-3">
                            <button
                                type="button"
                                @click="showCreateModal = false; resetCreateForm()"
                                class="flex-1 px-4 py-2 bg-gray-700 hover:bg-gray-600 text-white rounded-lg transition-colors"
                            >
                                Cancel
                            </button>
                            <button
                                type="submit"
                                :disabled="!newPlaylistName.trim() || isCreating"
                                class="flex-1 px-4 py-2 bg-green-500 hover:bg-green-600 disabled:bg-green-500/50 text-white font-semibold rounded-lg transition-colors"
                            >
                                {{ isCreating ? 'Creating...' : 'Create' }}
                            </button>
                        </div>
                    </form>
                </div>
            </div>
        </Teleport>
    </div>
</template>

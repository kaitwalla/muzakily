<script setup lang="ts">
import { onMounted, watch, ref, computed } from 'vue';
import { useRoute, useRouter } from 'vue-router';
import { usePlaylistsStore } from '@/stores/playlists';
import { usePlayerStore } from '@/stores/player';
import SmartPlaylistEditor from '@/components/playlist/SmartPlaylistEditor.vue';
import SongRow from '@/components/song/SongRow.vue';
import type { Song } from '@/types/models';
import type { SmartPlaylistRuleGroup } from '@/config/smartPlaylist';

const route = useRoute();
const router = useRouter();
const playlistsStore = usePlaylistsStore();
const playerStore = usePlayerStore();

const showDeleteConfirm = ref(false);
const isDeleting = ref(false);
const showEditModal = ref(false);
const editPlaylistName = ref('');
const editPlaylistDescription = ref('');
const editPlaylistRules = ref<SmartPlaylistRuleGroup[]>([]);
const isUpdating = ref(false);

const isSmartPlaylist = computed(() => playlistsStore.currentPlaylist?.is_smart ?? false);

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

function playSong(index: number): void {
    playerStore.play(playlistsStore.currentPlaylistSongs, index);
}

function handleSongUpdated(updatedSong: Song, index: number): void {
    playlistsStore.updateSongInPlaylist(updatedSong, index);
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

function openEditModal(): void {
    if (!playlistsStore.currentPlaylist) return;
    editPlaylistName.value = playlistsStore.currentPlaylist.name;
    editPlaylistDescription.value = playlistsStore.currentPlaylist.description ?? '';
    editPlaylistRules.value = playlistsStore.currentPlaylist.rules ?? [];
    showEditModal.value = true;
}

function closeEditModal(): void {
    showEditModal.value = false;
    editPlaylistName.value = '';
    editPlaylistDescription.value = '';
    editPlaylistRules.value = [];
}

function handleEditRulesUpdate(rules: SmartPlaylistRuleGroup[]): void {
    editPlaylistRules.value = rules;
}

async function handleEditSmartPlaylistSave(rules: SmartPlaylistRuleGroup[]): Promise<void> {
    editPlaylistRules.value = rules;
    await updatePlaylist();
}

async function updatePlaylist(): Promise<void> {
    if (!playlistsStore.currentPlaylist || !editPlaylistName.value.trim() || isUpdating.value) return;

    isUpdating.value = true;
    try {
        await playlistsStore.updatePlaylist(playlistsStore.currentPlaylist.id, {
            name: editPlaylistName.value.trim(),
            description: editPlaylistDescription.value.trim() || undefined,
            rules: isSmartPlaylist.value ? editPlaylistRules.value : undefined,
        });
        // Reload songs in case smart playlist rules changed
        if (isSmartPlaylist.value && playlistsStore.currentPlaylist) {
            await playlistsStore.fetchPlaylistSongs(playlistsStore.currentPlaylist.id);
        }
        closeEditModal();
    } catch {
        // Error handled by store
    } finally {
        isUpdating.value = false;
    }
}

function getTotalDuration(): string {
    const total = playlistsStore.currentPlaylistSongs.reduce((sum, song) => sum + song.length, 0);
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
            <p class="text-surface-400">Loading playlist...</p>
        </div>

        <div v-else-if="playlistsStore.error" class="text-center py-12">
            <p class="text-red-400">{{ playlistsStore.error }}</p>
        </div>

        <template v-else-if="playlistsStore.currentPlaylist">
            <!-- Playlist Header -->
            <div class="flex gap-6 mb-8">
                <div class="w-56 h-56 bg-surface-700 rounded-lg overflow-hidden flex-shrink-0 shadow-xl">
                    <img
                        v-if="playlistsStore.currentPlaylist.cover_url"
                        :src="playlistsStore.currentPlaylist.cover_url"
                        :alt="playlistsStore.currentPlaylist.name"
                        class="w-full h-full object-cover"
                    />
                    <div v-else class="w-full h-full flex items-center justify-center">
                        <svg class="w-24 h-24 text-surface-600" fill="currentColor" viewBox="0 0 24 24">
                            <path d="M15 6H3v2h12V6zm0 4H3v2h12v-2zM3 16h8v-2H3v2zM17 6v8.18c-.31-.11-.65-.18-1-.18-1.66 0-3 1.34-3 3s1.34 3 3 3 3-1.34 3-3V8h3V6h-5z"/>
                        </svg>
                    </div>
                </div>
                <div class="flex flex-col justify-end">
                    <p class="text-sm text-surface-400 uppercase font-medium">
                        {{ isSmartPlaylist ? 'Smart Playlist' : 'Playlist' }}
                    </p>
                    <h1 class="text-5xl font-bold text-white mt-2 mb-2">
                        {{ playlistsStore.currentPlaylist.name }}
                    </h1>
                    <p v-if="playlistsStore.currentPlaylist.description" class="text-surface-300 mb-4 max-w-2xl">
                        {{ playlistsStore.currentPlaylist.description }}
                    </p>
                    <div class="flex items-center gap-2 text-surface-400">
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
                    @click="openEditModal"
                    class="p-3 text-surface-400 hover:text-white transition-colors"
                    title="Edit playlist"
                >
                    <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15.232 5.232l3.536 3.536m-2.036-5.036a2.5 2.5 0 113.536 3.536L6.5 21.036H3v-3.572L16.732 3.732z"/>
                    </svg>
                </button>
                <button
                    @click="showDeleteConfirm = true"
                    class="p-3 text-surface-400 hover:text-white transition-colors"
                    title="Delete playlist"
                >
                    <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"/>
                    </svg>
                </button>
            </div>

            <!-- Songs List -->
            <div v-if="playlistsStore.currentPlaylistSongs.length > 0" class="bg-surface-800/50 rounded-lg overflow-hidden">
                <table class="w-full">
                    <thead class="border-b border-surface-700">
                        <tr class="text-left text-sm text-surface-400">
                            <th class="px-4 py-3 w-12"></th>
                            <th class="px-4 py-3">Title</th>
                            <th class="px-4 py-3">Album</th>
                            <th class="px-4 py-3 text-right">Duration</th>
                        </tr>
                    </thead>
                    <tbody>
                        <SongRow
                            v-for="(song, index) in playlistsStore.currentPlaylistSongs"
                            :key="song.id"
                            :song="song"
                            :index="index"
                            :show-track-number="false"
                            :show-artist="false"
                            :show-album="true"
                            @play="playSong(index)"
                            @updated="(updated) => handleSongUpdated(updated, index)"
                        />
                    </tbody>
                </table>
            </div>

            <div v-else class="text-center py-12 bg-surface-800/50 rounded-lg">
                <p class="text-surface-400">This playlist is empty</p>
                <p v-if="isSmartPlaylist" class="text-surface-500 text-sm mt-2">No songs match the current rules</p>
                <p v-else class="text-surface-500 text-sm mt-2">Add songs to get started</p>
            </div>
        </template>

        <!-- Delete Confirmation Modal -->
        <Teleport to="body">
            <div
                v-if="showDeleteConfirm"
                class="fixed inset-0 bg-black/50 flex items-center justify-center z-50"
                @click.self="showDeleteConfirm = false"
            >
                <div class="bg-surface-800 rounded-lg p-6 w-full max-w-sm">
                    <h2 class="text-xl font-bold text-white mb-2">Delete Playlist?</h2>
                    <p class="text-surface-400 mb-6">
                        This will permanently delete "{{ playlistsStore.currentPlaylist?.name }}".
                    </p>
                    <div class="flex gap-3">
                        <button
                            @click="showDeleteConfirm = false"
                            class="flex-1 px-4 py-2 bg-surface-700 hover:bg-surface-600 text-white rounded-lg transition-colors"
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

        <!-- Edit Playlist Modal -->
        <Teleport to="body">
            <div
                v-if="showEditModal"
                class="fixed inset-0 bg-black/50 flex items-center justify-center z-50 overflow-y-auto py-8"
                @click.self="closeEditModal"
            >
                <div
                    class="bg-surface-800 rounded-lg p-6 w-full mx-4 my-auto"
                    :class="isSmartPlaylist ? 'max-w-2xl' : 'max-w-md'"
                >
                    <h2 class="text-xl font-bold text-white mb-4">
                        Edit {{ isSmartPlaylist ? 'Smart Playlist' : 'Playlist' }}
                    </h2>
                    <form @submit.prevent="updatePlaylist">
                        <div class="mb-4">
                            <label for="edit-playlist-name" class="block text-sm font-medium text-surface-300 mb-2">
                                Name
                            </label>
                            <input
                                id="edit-playlist-name"
                                v-model="editPlaylistName"
                                type="text"
                                required
                                class="w-full px-4 py-2 bg-surface-700 border border-surface-600 rounded-lg text-white placeholder-surface-400 focus:outline-none focus:ring-2 focus:ring-green-500"
                                placeholder="Playlist name"
                            />
                        </div>
                        <div class="mb-6">
                            <label for="edit-playlist-description" class="block text-sm font-medium text-surface-300 mb-2">
                                Description (optional)
                            </label>
                            <textarea
                                id="edit-playlist-description"
                                v-model="editPlaylistDescription"
                                rows="3"
                                class="w-full px-4 py-2 bg-surface-700 border border-surface-600 rounded-lg text-white placeholder-surface-400 focus:outline-none focus:ring-2 focus:ring-green-500 resize-none"
                                placeholder="Add a description..."
                            />
                        </div>

                        <!-- Smart Playlist Editor (for smart playlists only) -->
                        <div v-if="isSmartPlaylist" class="mb-6">
                            <SmartPlaylistEditor
                                :initial-rules="editPlaylistRules"
                                @update:rules="handleEditRulesUpdate"
                                @save="handleEditSmartPlaylistSave"
                                @cancel="closeEditModal"
                            />
                        </div>

                        <!-- Regular playlist buttons (only show when not smart) -->
                        <div v-if="!isSmartPlaylist" class="flex gap-3">
                            <button
                                type="button"
                                @click="closeEditModal"
                                class="flex-1 px-4 py-2 bg-surface-700 hover:bg-surface-600 text-white rounded-lg transition-colors"
                            >
                                Cancel
                            </button>
                            <button
                                type="submit"
                                :disabled="!editPlaylistName.trim() || isUpdating"
                                class="flex-1 px-4 py-2 bg-green-500 hover:bg-green-600 disabled:bg-green-500/50 text-white font-semibold rounded-lg transition-colors"
                            >
                                {{ isUpdating ? 'Saving...' : 'Save' }}
                            </button>
                        </div>
                    </form>
                </div>
            </div>
        </Teleport>
    </div>
</template>

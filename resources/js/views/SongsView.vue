<script setup lang="ts">
import { ref, computed, onMounted } from 'vue';
import { useSongsStore } from '@/stores/songs';
import { usePlayerStore } from '@/stores/player';
import SongRow from '@/components/song/SongRow.vue';
import BulkEditModal from '@/components/song/BulkEditModal.vue';
import type { Song } from '@/types/models';

const songsStore = useSongsStore();
const playerStore = usePlayerStore();

const selectionMode = ref(false);
const selectedSongIds = ref<Set<string>>(new Set());
const showBulkEditModal = ref(false);

const selectedSongs = computed(() =>
    songsStore.songs.filter((s) => selectedSongIds.value.has(s.id))
);

const allSelected = computed(() =>
    songsStore.songs.length > 0 && selectedSongIds.value.size === songsStore.songs.length
);

onMounted(() => {
    songsStore.fetchSongs();
});

function playSong(index: number): void {
    if (!selectionMode.value) {
        playerStore.play(songsStore.songs, index);
    }
}

function playAllSongs(): void {
    if (songsStore.songs.length > 0) {
        playerStore.play(songsStore.songs, 0);
    }
}

function handleSongUpdated(updatedSong: Song, index: number): void {
    songsStore.updateSongInList(updatedSong, index);
}

function toggleSelectionMode(): void {
    selectionMode.value = !selectionMode.value;
    if (!selectionMode.value) {
        selectedSongIds.value.clear();
    }
}

function toggleSongSelection(songId: string): void {
    if (selectedSongIds.value.has(songId)) {
        selectedSongIds.value.delete(songId);
    } else {
        selectedSongIds.value.add(songId);
    }
    // Force reactivity
    selectedSongIds.value = new Set(selectedSongIds.value);
}

function selectAll(): void {
    selectedSongIds.value = new Set(songsStore.songs.map((s) => s.id));
}

function clearSelection(): void {
    selectedSongIds.value.clear();
    selectedSongIds.value = new Set();
}

function handleBulkUpdated(updatedSongs: Song[]): void {
    for (const updated of updatedSongs) {
        const index = songsStore.songs.findIndex((s) => s.id === updated.id);
        if (index !== -1) {
            songsStore.updateSongInList(updated, index);
        }
    }
    clearSelection();
    selectionMode.value = false;
}
</script>

<template>
    <div>
        <div class="flex items-center justify-between mb-6">
            <h1 class="text-3xl font-bold text-surface-50">Songs</h1>
            <div class="flex items-center gap-3">
                <button
                    v-if="songsStore.hasSongs"
                    @click="toggleSelectionMode"
                    class="px-4 py-2 text-surface-50 rounded-full transition-colors"
                    :class="selectionMode ? 'bg-surface-600 hover:bg-surface-500' : 'bg-surface-700 hover:bg-surface-600'"
                >
                    {{ selectionMode ? 'Cancel' : 'Select' }}
                </button>
                <button
                    v-if="songsStore.hasSongs && !selectionMode"
                    @click="playAllSongs"
                    class="px-4 py-2 bg-green-500 hover:bg-green-600 text-white font-semibold rounded-full transition-colors"
                >
                    Play All
                </button>
            </div>
        </div>

        <!-- Bulk action toolbar -->
        <div
            v-if="selectionMode && selectedSongIds.size > 0"
            class="mb-4 p-3 bg-surface-800 rounded-lg flex items-center justify-between"
        >
            <div class="flex items-center gap-4">
                <span class="text-surface-50">
                    {{ selectedSongIds.size }} selected
                </span>
                <button
                    @click="allSelected ? clearSelection() : selectAll()"
                    class="text-sm text-green-400 hover:text-green-300"
                >
                    {{ allSelected ? 'Deselect All' : 'Select All' }}
                </button>
            </div>
            <div class="flex items-center gap-2">
                <button
                    @click="showBulkEditModal = true"
                    class="px-4 py-2 bg-green-500 hover:bg-green-600 text-white font-semibold rounded-lg transition-colors"
                >
                    Bulk Edit
                </button>
            </div>
        </div>

        <div v-if="songsStore.loading && !songsStore.hasSongs" class="text-center py-12">
            <p class="text-surface-400">Loading songs...</p>
        </div>

        <div v-else-if="songsStore.error" class="text-center py-12">
            <p class="text-red-400">{{ songsStore.error }}</p>
        </div>

        <div v-else-if="!songsStore.hasSongs" class="text-center py-12">
            <p class="text-surface-400">No songs found</p>
        </div>

        <div v-else class="bg-surface-800 rounded-lg overflow-hidden">
            <table class="w-full">
                <thead class="border-b border-surface-700">
                    <tr class="text-left text-sm text-surface-400">
                        <th v-if="selectionMode" class="px-4 py-3 w-10">
                            <input
                                type="checkbox"
                                :checked="allSelected"
                                @change="allSelected ? clearSelection() : selectAll()"
                                class="w-4 h-4 rounded border-surface-500 bg-surface-700 text-green-500 focus:ring-green-500 focus:ring-offset-surface-800 cursor-pointer"
                            />
                        </th>
                        <th class="px-4 py-3 w-12"></th>
                        <th class="px-4 py-3">Title</th>
                        <th class="px-4 py-3">Artist</th>
                        <th class="px-4 py-3">Album</th>
                        <th class="px-4 py-3 text-right">Duration</th>
                    </tr>
                </thead>
                <tbody>
                    <SongRow
                        v-for="(song, index) in songsStore.songs"
                        :key="song.id"
                        :song="song"
                        :index="index"
                        :show-track-number="false"
                        :show-artist="true"
                        :show-album="true"
                        :selectable="selectionMode"
                        :selected="selectedSongIds.has(song.id)"
                        @play="playSong(index)"
                        @updated="(updated) => handleSongUpdated(updated, index)"
                        @toggle-select="toggleSongSelection(song.id)"
                    />
                </tbody>
            </table>
        </div>

        <div v-if="songsStore.hasMore" class="mt-6 text-center">
            <button
                @click="songsStore.loadMore"
                :disabled="songsStore.loading"
                class="px-6 py-2 bg-surface-700 hover:bg-surface-600 text-surface-50 rounded-full transition-colors disabled:opacity-50"
            >
                {{ songsStore.loading ? 'Loading...' : 'Load More' }}
            </button>
        </div>

        <!-- Bulk Edit Modal -->
        <BulkEditModal
            v-if="showBulkEditModal"
            :songs="selectedSongs"
            @close="showBulkEditModal = false"
            @updated="handleBulkUpdated"
        />
    </div>
</template>

<script setup lang="ts">
import { ref, computed, watch, onMounted } from 'vue';
import { useRoute, useRouter, RouterLink } from 'vue-router';
import { useSearchStore } from '@/stores/search';
import { usePlayerStore } from '@/stores/player';
import { useDebounceFn } from '@vueuse/core';
import SongRow from '@/components/song/SongRow.vue';
import BulkEditModal from '@/components/song/BulkEditModal.vue';
import type { Song } from '@/types/models';

const route = useRoute();
const router = useRouter();
const searchStore = useSearchStore();
const playerStore = usePlayerStore();

const searchInput = ref('');
const selectionMode = ref(false);
const selectedSongIds = ref<Set<string>>(new Set());
const showBulkEditModal = ref(false);

const displayedSongs = computed(() => searchStore.songs.slice(0, 5));

const selectedSongs = computed(() =>
    displayedSongs.value.filter((s) => selectedSongIds.value.has(s.id))
);

const allSelected = computed(() =>
    displayedSongs.value.length > 0 && selectedSongIds.value.size === displayedSongs.value.length
);

const debouncedSearch = useDebounceFn((query: string) => {
    const trimmed = query.trim();
    if (trimmed.length >= 2) {
        router.replace({ query: { q: query } });
        searchStore.search(query);
    } else {
        router.replace({ query: {} });
        searchStore.clearResults();
    }
}, 300);

watch(searchInput, (value) => {
    debouncedSearch(value);
});

// Clear selection when search results change
watch(() => searchStore.songs, () => {
    selectedSongIds.value.clear();
    selectedSongIds.value = new Set();
});

onMounted(() => {
    const query = route.query.q as string | undefined;
    if (query) {
        searchInput.value = query;
        if (query.trim().length >= 2) {
            searchStore.search(query);
        }
    }
});

function playSong(index: number): void {
    if (!selectionMode.value) {
        playerStore.play(displayedSongs.value, index);
    }
}

function toggleSelectionMode(): void {
    selectionMode.value = !selectionMode.value;
    if (!selectionMode.value) {
        selectedSongIds.value.clear();
        selectedSongIds.value = new Set();
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
    selectedSongIds.value = new Set(displayedSongs.value.map((s) => s.id));
}

function clearSelection(): void {
    selectedSongIds.value.clear();
    selectedSongIds.value = new Set();
}

function handleSongUpdated(updatedSong: Song): void {
    // Update in search store
    const storeIndex = searchStore.songs.findIndex((s) => s.id === updatedSong.id);
    if (storeIndex !== -1) {
        searchStore.songs[storeIndex] = updatedSong;
    }
}

function handleBulkUpdated(updatedSongs: Song[]): void {
    for (const updated of updatedSongs) {
        const index = searchStore.songs.findIndex((s) => s.id === updated.id);
        if (index !== -1) {
            searchStore.songs[index] = updated;
        }
    }
    clearSelection();
    selectionMode.value = false;
}
</script>

<template>
    <div>
        <div class="mb-6">
            <input
                v-model="searchInput"
                type="search"
                placeholder="Search for songs, albums, artists..."
                class="w-full px-6 py-4 bg-surface-800 border border-surface-700 rounded-full text-white placeholder-surface-400 focus:outline-none focus:ring-2 focus:ring-green-500 focus:border-transparent text-lg"
            />
        </div>

        <div v-if="searchStore.loading" class="text-center py-12">
            <p class="text-surface-400">Searching...</p>
        </div>

        <div v-else-if="searchStore.error" class="text-center py-12">
            <p class="text-red-400">{{ searchStore.error }}</p>
        </div>

        <div v-else-if="searchStore.hasSearched && !searchStore.hasResults" class="text-center py-12">
            <p class="text-surface-400">No results found for "{{ searchStore.query }}"</p>
        </div>

        <div v-else-if="searchStore.hasResults">
            <!-- Songs -->
            <section v-if="searchStore.songs.length > 0" class="mb-8">
                <div class="flex items-center justify-between mb-4">
                    <h2 class="text-xl font-semibold text-white">Songs</h2>
                    <button
                        @click="toggleSelectionMode"
                        class="px-4 py-2 text-sm text-surface-50 rounded-full transition-colors"
                        :class="selectionMode ? 'bg-surface-600 hover:bg-surface-500' : 'bg-surface-700 hover:bg-surface-600'"
                    >
                        {{ selectionMode ? 'Cancel' : 'Select' }}
                    </button>
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

                <div class="bg-surface-800 rounded-lg overflow-hidden">
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
                                v-for="(song, index) in displayedSongs"
                                :key="song.id"
                                :song="song"
                                :index="index"
                                :show-track-number="false"
                                :show-artist="true"
                                :show-album="true"
                                :selectable="selectionMode"
                                :selected="selectedSongIds.has(song.id)"
                                @play="playSong(index)"
                                @updated="handleSongUpdated"
                                @toggle-select="toggleSongSelection(song.id)"
                            />
                        </tbody>
                    </table>
                </div>
            </section>

            <!-- Albums -->
            <section v-if="searchStore.albums.length > 0" class="mb-8">
                <h2 class="text-xl font-semibold text-white mb-4">Albums</h2>
                <div class="grid grid-cols-2 md:grid-cols-3 lg:grid-cols-4 xl:grid-cols-5 gap-4">
                    <RouterLink
                        v-for="album in searchStore.albums.slice(0, 5)"
                        :key="album.id"
                        :to="{ name: 'album-detail', params: { slug: album.id } }"
                        class="bg-surface-800 rounded-lg p-4 hover:bg-surface-700 transition-colors"
                    >
                        <div class="aspect-square bg-surface-700 rounded-lg mb-3 overflow-hidden">
                            <img
                                v-if="album.cover"
                                :src="album.cover"
                                :alt="album.name"
                                class="w-full h-full object-cover"
                            />
                        </div>
                        <p class="text-white font-medium truncate">{{ album.name }}</p>
                        <p class="text-surface-400 text-sm truncate">{{ album.artist_name ?? 'Unknown' }}</p>
                    </RouterLink>
                </div>
            </section>

            <!-- Artists -->
            <section v-if="searchStore.artists.length > 0" class="mb-8">
                <h2 class="text-xl font-semibold text-white mb-4">Artists</h2>
                <div class="grid grid-cols-2 md:grid-cols-3 lg:grid-cols-4 xl:grid-cols-5 gap-4">
                    <RouterLink
                        v-for="artist in searchStore.artists.slice(0, 5)"
                        :key="artist.id"
                        :to="{ name: 'artist-detail', params: { slug: artist.id } }"
                        class="bg-surface-800 rounded-lg p-4 hover:bg-surface-700 transition-colors text-center"
                    >
                        <div class="aspect-square bg-surface-700 rounded-full mb-3 mx-auto overflow-hidden w-24 h-24">
                            <img
                                v-if="artist.image"
                                :src="artist.image"
                                :alt="artist.name"
                                class="w-full h-full object-cover"
                            />
                        </div>
                        <p class="text-white font-medium truncate">{{ artist.name }}</p>
                        <p class="text-surface-500 text-sm">Artist</p>
                    </RouterLink>
                </div>
            </section>

            <!-- Playlists -->
            <section v-if="searchStore.playlists.length > 0" class="mb-8">
                <h2 class="text-xl font-semibold text-white mb-4">Playlists</h2>
                <div class="grid grid-cols-2 md:grid-cols-3 lg:grid-cols-4 xl:grid-cols-5 gap-4">
                    <RouterLink
                        v-for="playlist in searchStore.playlists.slice(0, 5)"
                        :key="playlist.id"
                        :to="{ name: 'playlist-detail', params: { slug: playlist.slug } }"
                        class="bg-surface-800 rounded-lg p-4 hover:bg-surface-700 transition-colors"
                    >
                        <div class="aspect-square bg-surface-700 rounded-lg mb-3 overflow-hidden">
                            <img
                                v-if="playlist.cover_url"
                                :src="playlist.cover_url"
                                :alt="playlist.name"
                                class="w-full h-full object-cover"
                            />
                        </div>
                        <p class="text-white font-medium truncate">{{ playlist.name }}</p>
                        <p class="text-surface-400 text-sm truncate">{{ playlist.songs_count ?? 0 }} songs</p>
                    </RouterLink>
                </div>
            </section>
        </div>

        <div v-else class="text-center py-12">
            <p class="text-surface-400">Search for your favorite music</p>
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

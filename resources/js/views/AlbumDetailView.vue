<script setup lang="ts">
import { onMounted, watch, ref } from 'vue';
import { useRoute, RouterLink } from 'vue-router';
import { useAlbumsStore } from '@/stores/albums';
import { usePlayerStore } from '@/stores/player';
import SongRow from '@/components/song/SongRow.vue';
import type { Song } from '@/types/models';

const route = useRoute();
const albumsStore = useAlbumsStore();
const playerStore = usePlayerStore();

const showCoverMenu = ref(false);
const coverLoading = ref(false);
const coverFileInput = ref<HTMLInputElement | null>(null);

async function loadAlbum(): Promise<void> {
    const slug = route.params.slug as string;
    const album = await albumsStore.fetchAlbum(slug);
    await albumsStore.fetchAlbumSongs(album.id);
}

onMounted(() => {
    loadAlbum();
});

watch(() => route.params.slug, () => {
    loadAlbum();
});

function playAlbum(): void {
    if (albumsStore.currentAlbumSongs.length > 0) {
        playerStore.play(albumsStore.currentAlbumSongs, 0);
    }
}

function playSong(index: number): void {
    playerStore.play(albumsStore.currentAlbumSongs, index);
}

function handleSongUpdated(updatedSong: Song, index: number): void {
    albumsStore.updateSongInAlbum(updatedSong, index);
}

function getTotalDuration(): string {
    const total = albumsStore.currentAlbumSongs.reduce((sum, song) => sum + song.length, 0);
    const hours = Math.floor(total / 3600);
    const mins = Math.floor((total % 3600) / 60);
    if (hours > 0) {
        return `${hours} hr ${mins} min`;
    }
    return `${mins} min`;
}

function triggerCoverUpload(): void {
    showCoverMenu.value = false;
    coverFileInput.value?.click();
}

async function handleCoverUpload(event: Event): Promise<void> {
    const input = event.target as HTMLInputElement;
    const file = input.files?.[0];
    if (!file || !albumsStore.currentAlbum) return;

    coverLoading.value = true;
    try {
        await albumsStore.uploadCover(albumsStore.currentAlbum.id, file);
    } catch (e) {
        // Error handled in store
    } finally {
        coverLoading.value = false;
        input.value = '';
    }
}

async function refreshCover(): Promise<void> {
    if (!albumsStore.currentAlbum) return;

    showCoverMenu.value = false;
    coverLoading.value = true;
    try {
        await albumsStore.refreshCover(albumsStore.currentAlbum.id);
    } catch (e) {
        // Error handled in store
    } finally {
        coverLoading.value = false;
    }
}
</script>

<template>
    <div>
        <div v-if="albumsStore.loading && !albumsStore.currentAlbum" class="text-center py-12">
            <p class="text-surface-400">Loading album...</p>
        </div>

        <div v-else-if="albumsStore.error" class="text-center py-12">
            <p class="text-red-400">{{ albumsStore.error }}</p>
        </div>

        <template v-else-if="albumsStore.currentAlbum">
            <!-- Album Header -->
            <div class="flex gap-6 mb-8">
                <div class="relative group">
                    <div class="relative w-56 h-56 bg-surface-700 rounded-lg overflow-hidden flex-shrink-0 shadow-xl">
                        <img
                            v-if="albumsStore.currentAlbum.cover"
                            :src="albumsStore.currentAlbum.cover"
                            :alt="albumsStore.currentAlbum.name"
                            class="w-full h-full object-cover"
                        />
                        <div v-else class="w-full h-full flex items-center justify-center">
                            <svg class="w-24 h-24 text-surface-600" fill="currentColor" viewBox="0 0 24 24">
                                <path d="M12 2C6.48 2 2 6.48 2 12s4.48 10 10 10 10-4.48 10-10S17.52 2 12 2zm0 14.5c-2.49 0-4.5-2.01-4.5-4.5S9.51 7.5 12 7.5s4.5 2.01 4.5 4.5-2.01 4.5-4.5 4.5zm0-5.5c-.55 0-1 .45-1 1s.45 1 1 1 1-.45 1-1-.45-1-1-1z"/>
                            </svg>
                        </div>
                        <!-- Loading overlay -->
                        <div v-if="coverLoading" class="absolute inset-0 bg-black/50 flex items-center justify-center">
                            <svg class="w-8 h-8 text-white animate-spin" fill="none" viewBox="0 0 24 24">
                                <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4" />
                                <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z" />
                            </svg>
                        </div>
                    </div>
                    <!-- Cover edit button -->
                    <button
                        @click="showCoverMenu = !showCoverMenu"
                        class="absolute bottom-2 right-2 p-2 bg-black/70 rounded-full opacity-0 group-hover:opacity-100 transition-opacity hover:bg-black/90"
                        title="Edit cover"
                    >
                        <svg class="w-4 h-4 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15.232 5.232l3.536 3.536m-2.036-5.036a2.5 2.5 0 113.536 3.536L6.5 21.036H3v-3.572L16.732 3.732z" />
                        </svg>
                    </button>
                    <!-- Cover menu backdrop -->
                    <div
                        v-if="showCoverMenu"
                        class="fixed inset-0 z-10"
                        @click="showCoverMenu = false"
                    />
                    <!-- Cover menu dropdown -->
                    <div
                        v-if="showCoverMenu"
                        class="absolute bottom-14 right-2 bg-surface-700 rounded-lg shadow-xl py-1 min-w-40 z-20"
                    >
                        <button
                            @click="triggerCoverUpload"
                            class="w-full px-4 py-2 text-left text-sm text-white hover:bg-surface-600 flex items-center gap-2"
                        >
                            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16l4.586-4.586a2 2 0 012.828 0L16 16m-2-2l1.586-1.586a2 2 0 012.828 0L20 14m-6-6h.01M6 20h12a2 2 0 002-2V6a2 2 0 00-2-2H6a2 2 0 00-2 2v12a2 2 0 002 2z" />
                            </svg>
                            Upload cover
                        </button>
                        <button
                            @click="refreshCover"
                            class="w-full px-4 py-2 text-left text-sm text-white hover:bg-surface-600 flex items-center gap-2"
                        >
                            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15" />
                            </svg>
                            Fetch from MusicBrainz
                        </button>
                    </div>
                    <!-- Hidden file input -->
                    <input
                        ref="coverFileInput"
                        type="file"
                        accept="image/jpeg,image/png,image/webp"
                        class="hidden"
                        @change="handleCoverUpload"
                    />
                </div>
                <div class="flex flex-col justify-end">
                    <p class="text-sm text-surface-400 uppercase font-medium">Album</p>
                    <h1 class="text-5xl font-bold text-white mt-2 mb-4">
                        {{ albumsStore.currentAlbum.name }}
                    </h1>
                    <div class="flex items-center gap-2 text-surface-300">
                        <RouterLink
                            v-if="albumsStore.currentAlbum.artist_id"
                            :to="{ name: 'artist-detail', params: { slug: albumsStore.currentAlbum.artist_id } }"
                            class="font-medium hover:underline"
                        >
                            {{ albumsStore.currentAlbum.artist_name }}
                        </RouterLink>
                        <span v-if="albumsStore.currentAlbum.year" class="text-surface-400">
                            &bull; {{ albumsStore.currentAlbum.year }}
                        </span>
                        <span class="text-surface-400">
                            &bull; {{ albumsStore.currentAlbumSongs.length }} songs, {{ getTotalDuration() }}
                        </span>
                    </div>
                </div>
            </div>

            <!-- Play Button -->
            <div class="mb-6">
                <button
                    @click="playAlbum"
                    :disabled="albumsStore.currentAlbumSongs.length === 0"
                    class="w-14 h-14 bg-green-500 hover:bg-green-400 hover:scale-105 rounded-full flex items-center justify-center transition-all disabled:opacity-50"
                >
                    <svg class="w-6 h-6 text-black ml-1" fill="currentColor" viewBox="0 0 24 24">
                        <path d="M8 5v14l11-7z"/>
                    </svg>
                </button>
            </div>

            <!-- Songs List -->
            <div class="bg-surface-800/50 rounded-lg overflow-hidden">
                <table class="w-full">
                    <thead class="border-b border-surface-700">
                        <tr class="text-left text-sm text-surface-400">
                            <th class="px-4 py-3 w-12">#</th>
                            <th class="px-4 py-3">Title</th>
                            <th class="px-4 py-3 text-right">Duration</th>
                        </tr>
                    </thead>
                    <tbody>
                        <SongRow
                            v-for="(song, index) in albumsStore.currentAlbumSongs"
                            :key="song.id"
                            :song="song"
                            :index="index"
                            :show-track-number="true"
                            :show-artist="false"
                            :show-album="false"
                            @play="playSong(index)"
                            @updated="(updated) => handleSongUpdated(updated, index)"
                        />
                    </tbody>
                </table>
            </div>
        </template>
    </div>
</template>

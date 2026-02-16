<script setup lang="ts">
import { ref, computed, watch, nextTick } from 'vue';
import { RouterLink } from 'vue-router';
import type { Song } from '@/types/models';
import { usePlayerStore } from '@/stores/player';
import { toggleFavorite } from '@/api/favorites';
import { downloadSong } from '@/api/songs';
import SongDetailsModal from './SongDetailsModal.vue';

interface Props {
    song: Song;
    index: number;
    showTrackNumber?: boolean;
    showArtist?: boolean;
    showAlbum?: boolean;
    selectable?: boolean;
    selected?: boolean;
}

const props = withDefaults(defineProps<Props>(), {
    showTrackNumber: false,
    showArtist: true,
    showAlbum: true,
    selectable: false,
    selected: false,
});

const emit = defineEmits<{
    play: [];
    updated: [song: Song];
    'toggle-select': [];
}>();

const playerStore = usePlayerStore();

const isPlaying = computed(() =>
    playerStore.currentSong?.id === props.song.id && playerStore.isPlaying
);

const isCurrentSong = computed(() =>
    playerStore.currentSong?.id === props.song.id
);

const isFavorite = ref(props.song.is_favorite);
const favoriteLoading = ref(false);
const showMenu = ref(false);
const showDetailsModal = ref(false);
const menuButtonRef = ref<HTMLButtonElement | null>(null);
const menuPosition = ref({ top: 0, left: 0 });

// Sync favorite status when song prop changes
watch(() => props.song.is_favorite, (newValue) => {
    isFavorite.value = newValue;
});

async function handleFavoriteClick(event: Event): Promise<void> {
    event.stopPropagation();
    if (favoriteLoading.value) return;

    favoriteLoading.value = true;
    try {
        await toggleFavorite(props.song.id, isFavorite.value);
        isFavorite.value = !isFavorite.value;
    } catch (error) {
        // Log the error for debugging - the UI state remains unchanged
        console.error('Failed to toggle favorite:', error);
    } finally {
        favoriteLoading.value = false;
    }
}

async function handleMenuClick(event: Event): Promise<void> {
    event.stopPropagation();
    if (showMenu.value) {
        showMenu.value = false;
        return;
    }

    // Calculate position before showing menu
    await nextTick();
    if (menuButtonRef.value) {
        const rect = menuButtonRef.value.getBoundingClientRect();
        menuPosition.value = {
            top: rect.bottom + 4,
            left: rect.right - 192, // 192px = w-48 menu width
        };
    }
    showMenu.value = true;
}

function openDetailsModal(): void {
    showMenu.value = false;
    showDetailsModal.value = true;
}

function handleSongUpdated(updatedSong: Song): void {
    isFavorite.value = updatedSong.is_favorite;
    emit('updated', updatedSong);
}

function formatDuration(seconds: number): string {
    const mins = Math.floor(seconds / 60);
    const secs = Math.floor(seconds % 60);
    return `${mins}:${secs.toString().padStart(2, '0')}`;
}

function closeMenu(): void {
    showMenu.value = false;
}

function handleCheckboxClick(event: Event): void {
    event.stopPropagation();
    emit('toggle-select');
}

async function handleDownload(): Promise<void> {
    showMenu.value = false;
    await downloadSong(props.song.id);
}
</script>

<template>
    <tr
        @click="emit('play')"
        class="hover:bg-gray-700 cursor-pointer transition-colors group"
        :class="{ 'bg-gray-700': isCurrentSong, 'bg-green-900/20': selected }"
    >
        <!-- Selection checkbox -->
        <td v-if="selectable" class="px-4 py-3 w-10">
            <input
                type="checkbox"
                :checked="selected"
                @click="handleCheckboxClick"
                class="w-4 h-4 rounded border-surface-500 bg-surface-700 text-green-500 focus:ring-green-500 focus:ring-offset-surface-800 cursor-pointer"
            />
        </td>

        <!-- Favorite + Track Number (in album view) or just Favorite -->
        <td class="px-4 py-3" :class="showTrackNumber ? 'w-20' : 'w-12'">
            <div class="flex items-center gap-3">
                <!-- Favorite heart - always visible -->
                <button
                    @click="handleFavoriteClick"
                    :disabled="favoriteLoading"
                    class="text-gray-400 hover:text-white transition-colors disabled:opacity-50 flex-shrink-0"
                    :class="{ 'text-green-500 hover:text-green-400': isFavorite }"
                    :aria-label="isFavorite ? 'Remove from favorites' : 'Add to favorites'"
                >
                    <svg class="w-5 h-5" :fill="isFavorite ? 'currentColor' : 'none'" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4.318 6.318a4.5 4.5 0 000 6.364L12 20.364l7.682-7.682a4.5 4.5 0 00-6.364-6.364L12 7.636l-1.318-1.318a4.5 4.5 0 00-6.364 0z" />
                    </svg>
                </button>
                <!-- Track number or playing indicator (in album view) -->
                <template v-if="showTrackNumber">
                    <span v-if="isPlaying" class="text-green-500">
                        <svg class="w-4 h-4 animate-pulse" fill="currentColor" viewBox="0 0 24 24">
                            <path d="M8 5v14l11-7z"/>
                        </svg>
                    </span>
                    <span v-else class="text-gray-400 w-5 text-center">
                        {{ song.track ?? index + 1 }}
                    </span>
                </template>
                <!-- Playing indicator only (non-album view) -->
                <span v-else-if="isPlaying" class="text-green-500">
                    <svg class="w-4 h-4 animate-pulse" fill="currentColor" viewBox="0 0 24 24">
                        <path d="M8 5v14l11-7z"/>
                    </svg>
                </span>
            </div>
        </td>

        <!-- Title (with optional subtitle) -->
        <td class="px-4 py-3">
            <p class="text-white font-medium" :class="{ 'text-green-500': isCurrentSong }">
                {{ song.title }}
            </p>
            <!-- Show artist as subtitle when we don't have a separate artist column but do have album column (playlist view) -->
            <template v-if="!showArtist && showAlbum && song.artist_name">
                <RouterLink
                    v-if="song.artist_slug"
                    :to="{ name: 'artist-detail', params: { slug: song.artist_slug } }"
                    class="text-gray-400 text-sm hover:text-white hover:underline"
                    @click.stop
                >
                    {{ song.artist_name }}
                </RouterLink>
                <span v-else class="text-gray-400 text-sm">{{ song.artist_name }}</span>
            </template>
            <!-- Show album as subtitle when we don't have separate artist or album columns (artist view) -->
            <template v-else-if="!showArtist && !showAlbum && song.album_name">
                <RouterLink
                    v-if="song.album_slug"
                    :to="{ name: 'album-detail', params: { slug: song.album_slug } }"
                    class="text-gray-400 text-sm hover:text-white hover:underline"
                    @click.stop
                >
                    {{ song.album_name }}
                </RouterLink>
                <span v-else class="text-gray-400 text-sm">{{ song.album_name }}</span>
            </template>
        </td>

        <!-- Artist column (optional) -->
        <td v-if="showArtist" class="px-4 py-3 text-gray-400">
            <RouterLink
                v-if="song.artist_slug"
                :to="{ name: 'artist-detail', params: { slug: song.artist_slug } }"
                class="hover:text-white hover:underline"
                @click.stop
            >
                {{ song.artist_name }}
            </RouterLink>
            <span v-else>{{ song.artist_name ?? 'Unknown' }}</span>
        </td>

        <!-- Album column (optional) -->
        <td v-if="showAlbum" class="px-4 py-3 text-gray-400">
            <RouterLink
                v-if="song.album_slug"
                :to="{ name: 'album-detail', params: { slug: song.album_slug } }"
                class="hover:text-white hover:underline"
                @click.stop
            >
                {{ song.album_name }}
            </RouterLink>
            <span v-else>{{ song.album_name ?? '-' }}</span>
        </td>

        <!-- Duration and Menu -->
        <td class="px-4 py-3 text-gray-400">
            <div class="flex items-center justify-end gap-2">
                <span>{{ formatDuration(song.length) }}</span>
                <div class="relative">
                    <button
                        ref="menuButtonRef"
                        @click="handleMenuClick"
                        class="p-1 opacity-0 group-hover:opacity-100 text-gray-400 hover:text-white transition-all"
                        aria-label="More options"
                    >
                        <svg class="w-5 h-5" fill="currentColor" viewBox="0 0 24 24">
                            <path d="M12 8c1.1 0 2-.9 2-2s-.9-2-2-2-2 .9-2 2 .9 2 2 2zm0 2c-1.1 0-2 .9-2 2s.9 2 2 2 2-.9 2-2-.9-2-2-2zm0 6c-1.1 0-2 .9-2 2s.9 2 2 2 2-.9 2-2-.9-2-2-2z"/>
                        </svg>
                    </button>

                    <!-- Dropdown Menu (Teleported to body to escape overflow:hidden) -->
                    <Teleport to="body">
                        <div
                            v-if="showMenu"
                            class="fixed inset-0 z-40"
                            @click="closeMenu"
                        />
                        <div
                            v-if="showMenu"
                            class="fixed w-48 bg-gray-700 rounded-lg shadow-lg py-1 z-50"
                            :style="{ top: menuPosition.top + 'px', left: menuPosition.left + 'px' }"
                            @click.stop
                        >
                            <button
                                @click.stop="openDetailsModal"
                                class="w-full px-4 py-2 text-left text-gray-200 hover:bg-gray-600 transition-colors flex items-center gap-3"
                            >
                                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15.232 5.232l3.536 3.536m-2.036-5.036a2.5 2.5 0 113.536 3.536L6.5 21.036H3v-3.572L16.732 3.732z"/>
                                </svg>
                                Edit details
                            </button>
                            <button
                                @click.stop="handleDownload"
                                class="w-full px-4 py-2 text-left text-gray-200 hover:bg-gray-600 transition-colors flex items-center gap-3"
                            >
                                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-4l-4 4m0 0l-4-4m4 4V4"/>
                                </svg>
                                Download
                            </button>
                        </div>
                    </Teleport>
                </div>
            </div>
        </td>
    </tr>

    <!-- Details Modal -->
    <SongDetailsModal
        v-if="showDetailsModal"
        :song="song"
        @close="showDetailsModal = false"
        @updated="handleSongUpdated"
    />
</template>

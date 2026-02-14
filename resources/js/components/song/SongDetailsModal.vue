<script setup lang="ts">
import { ref, watch, computed } from 'vue';
import type { Song } from '@/types/models';
import { updateSong, addTagsToSong, removeTagsFromSong, type UpdateSongData } from '@/api/songs';
import TagPicker from './TagPicker.vue';

interface Props {
    song: Song;
}

const props = defineProps<Props>();

const emit = defineEmits<{
    close: [];
    updated: [song: Song];
}>();

const loading = ref(false);
const error = ref<string | null>(null);

const formData = ref<UpdateSongData>({
    title: '',
    artist_name: null,
    album_name: null,
    year: null,
    track: null,
    disc: null,
    genre: null,
});

const selectedTagIds = ref<number[]>([]);
const initialTagIds = ref<number[]>([]);

watch(
    () => props.song,
    (song) => {
        formData.value = {
            title: song.title,
            artist_name: song.artist_name,
            album_name: song.album_name,
            year: song.year,
            track: song.track,
            disc: song.disc,
            genre: song.genre,
        };
        const tagIds = song.tags?.map((t) => t.id) ?? [];
        selectedTagIds.value = [...tagIds];
        initialTagIds.value = [...tagIds];
    },
    { immediate: true }
);

// Computed properties to handle nullable numbers properly
// v-model.number converts empty strings to 0, but we want null
const yearModel = computed({
    get: () => formData.value.year ?? '',
    set: (val: string | number) => {
        formData.value.year = val === '' || val === 0 ? null : Number(val);
    },
});

const trackModel = computed({
    get: () => formData.value.track ?? '',
    set: (val: string | number) => {
        formData.value.track = val === '' || val === 0 ? null : Number(val);
    },
});

const discModel = computed({
    get: () => formData.value.disc ?? '',
    set: (val: string | number) => {
        formData.value.disc = val === '' || val === 0 ? null : Number(val);
    },
});

async function handleSubmit(): Promise<void> {
    if (!formData.value.title?.trim()) return;

    loading.value = true;
    error.value = null;

    try {
        let updated = await updateSong(props.song.id, formData.value);

        // Handle tag changes
        const tagsToAdd = selectedTagIds.value.filter((id) => !initialTagIds.value.includes(id));
        const tagsToRemove = initialTagIds.value.filter((id) => !selectedTagIds.value.includes(id));

        if (tagsToAdd.length > 0) {
            updated = await addTagsToSong(props.song.id, tagsToAdd);
        }
        if (tagsToRemove.length > 0) {
            updated = await removeTagsFromSong(props.song.id, tagsToRemove);
        }

        emit('updated', updated);
        emit('close');
    } catch (e) {
        error.value = e instanceof Error ? e.message : 'Failed to update song';
    } finally {
        loading.value = false;
    }
}

function formatDuration(seconds: number): string {
    const mins = Math.floor(seconds / 60);
    const secs = Math.floor(seconds % 60);
    return `${mins}:${secs.toString().padStart(2, '0')}`;
}
</script>

<template>
    <Teleport to="body">
        <div
            class="fixed inset-0 bg-black/50 flex items-center justify-center z-50"
            @click.self="emit('close')"
        >
            <div class="bg-gray-800 rounded-lg w-full max-w-lg max-h-[90vh] flex flex-col">
                <!-- Header -->
                <div class="flex items-center justify-between px-6 py-4 border-b border-gray-700">
                    <h2 class="text-xl font-bold text-white">Song Details</h2>
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

                <!-- Song info header -->
                <div class="px-6 py-4 border-b border-gray-700 flex items-center gap-4">
                    <div class="w-16 h-16 bg-gray-700 rounded overflow-hidden flex-shrink-0">
                        <img
                            v-if="song.album_cover"
                            :src="song.album_cover"
                            :alt="song.album_name ?? 'Album cover'"
                            class="w-full h-full object-cover"
                        />
                        <div v-else class="w-full h-full flex items-center justify-center">
                            <svg class="w-8 h-8 text-gray-600" fill="currentColor" viewBox="0 0 24 24">
                                <path d="M12 3v10.55c-.59-.34-1.27-.55-2-.55-2.21 0-4 1.79-4 4s1.79 4 4 4 4-1.79 4-4V7h4V3h-6z"/>
                            </svg>
                        </div>
                    </div>
                    <div class="flex-1 min-w-0">
                        <p class="text-white font-medium truncate">{{ song.title }}</p>
                        <p class="text-gray-400 text-sm truncate">{{ song.artist_name ?? 'Unknown Artist' }}</p>
                        <p class="text-gray-500 text-sm">{{ formatDuration(song.length) }} &bull; {{ song.audio_format.toUpperCase() }}</p>
                    </div>
                </div>

                <!-- File path -->
                <div class="px-6 py-3 border-b border-surface-700 bg-surface-900/50">
                    <p class="text-xs text-surface-500 mb-1">File Path</p>
                    <p class="text-sm text-surface-400 font-mono break-all">{{ song.storage_path }}</p>
                </div>

                <!-- Form -->
                <form @submit.prevent="handleSubmit" class="flex-1 overflow-y-auto px-6 py-4">
                    <div class="space-y-4">
                        <!-- Title -->
                        <div>
                            <label for="song-title" class="block text-sm font-medium text-gray-300 mb-1">
                                Title
                            </label>
                            <input
                                id="song-title"
                                v-model="formData.title"
                                type="text"
                                required
                                class="w-full px-4 py-2 bg-gray-700 border border-gray-600 rounded-lg text-white placeholder-gray-400 focus:outline-none focus:ring-2 focus:ring-green-500"
                            />
                        </div>

                        <!-- Artist -->
                        <div>
                            <label for="song-artist" class="block text-sm font-medium text-gray-300 mb-1">
                                Artist
                            </label>
                            <input
                                id="song-artist"
                                v-model="formData.artist_name"
                                type="text"
                                class="w-full px-4 py-2 bg-gray-700 border border-gray-600 rounded-lg text-white placeholder-gray-400 focus:outline-none focus:ring-2 focus:ring-green-500"
                            />
                        </div>

                        <!-- Album -->
                        <div>
                            <label for="song-album" class="block text-sm font-medium text-gray-300 mb-1">
                                Album
                            </label>
                            <input
                                id="song-album"
                                v-model="formData.album_name"
                                type="text"
                                class="w-full px-4 py-2 bg-gray-700 border border-gray-600 rounded-lg text-white placeholder-gray-400 focus:outline-none focus:ring-2 focus:ring-green-500"
                            />
                        </div>

                        <!-- Year and Track row -->
                        <div class="grid grid-cols-3 gap-4">
                            <div>
                                <label for="song-year" class="block text-sm font-medium text-gray-300 mb-1">
                                    Year
                                </label>
                                <input
                                    id="song-year"
                                    v-model="yearModel"
                                    type="number"
                                    min="1900"
                                    :max="new Date().getFullYear() + 1"
                                    class="w-full px-4 py-2 bg-gray-700 border border-gray-600 rounded-lg text-white placeholder-gray-400 focus:outline-none focus:ring-2 focus:ring-green-500"
                                />
                            </div>
                            <div>
                                <label for="song-track" class="block text-sm font-medium text-gray-300 mb-1">
                                    Track
                                </label>
                                <input
                                    id="song-track"
                                    v-model="trackModel"
                                    type="number"
                                    min="1"
                                    class="w-full px-4 py-2 bg-gray-700 border border-gray-600 rounded-lg text-white placeholder-gray-400 focus:outline-none focus:ring-2 focus:ring-green-500"
                                />
                            </div>
                            <div>
                                <label for="song-disc" class="block text-sm font-medium text-gray-300 mb-1">
                                    Disc
                                </label>
                                <input
                                    id="song-disc"
                                    v-model="discModel"
                                    type="number"
                                    min="1"
                                    class="w-full px-4 py-2 bg-gray-700 border border-gray-600 rounded-lg text-white placeholder-gray-400 focus:outline-none focus:ring-2 focus:ring-green-500"
                                />
                            </div>
                        </div>

                        <!-- Genre -->
                        <div>
                            <label for="song-genre" class="block text-sm font-medium text-gray-300 mb-1">
                                Genre
                            </label>
                            <input
                                id="song-genre"
                                v-model="formData.genre"
                                type="text"
                                class="w-full px-4 py-2 bg-gray-700 border border-gray-600 rounded-lg text-white placeholder-gray-400 focus:outline-none focus:ring-2 focus:ring-green-500"
                            />
                        </div>

                        <!-- Tags -->
                        <TagPicker
                            :selected-tag-ids="selectedTagIds"
                            @update:selected-tag-ids="selectedTagIds = $event"
                        />
                    </div>
                </form>

                <!-- Footer -->
                <div class="px-6 py-4 border-t border-gray-700 flex gap-3">
                    <button
                        type="button"
                        @click="emit('close')"
                        class="flex-1 px-4 py-2 bg-gray-700 hover:bg-gray-600 text-white rounded-lg transition-colors"
                    >
                        Cancel
                    </button>
                    <button
                        @click="handleSubmit"
                        :disabled="!formData.title?.trim() || loading"
                        class="flex-1 px-4 py-2 bg-green-500 hover:bg-green-600 disabled:bg-green-500/50 text-white font-semibold rounded-lg transition-colors"
                    >
                        {{ loading ? 'Saving...' : 'Save' }}
                    </button>
                </div>
            </div>
        </div>
    </Teleport>
</template>

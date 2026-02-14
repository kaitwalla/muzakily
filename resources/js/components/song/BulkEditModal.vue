<script setup lang="ts">
import { ref, computed, onMounted } from 'vue';
import type { Song, Tag } from '@/types/models';
import { bulkUpdateSongs, type BulkUpdateSongsData } from '@/api/songs';
import { getTags } from '@/api/tags';

interface Props {
    songs: Song[];
}

const props = defineProps<Props>();

const emit = defineEmits<{
    close: [];
    updated: [songs: Song[]];
}>();

const loading = ref(false);
const error = ref<string | null>(null);
const availableTags = ref<Tag[]>([]);
const tagsLoading = ref(false);

const formData = ref<BulkUpdateSongsData>({
    artist_name: undefined,
    album_name: undefined,
    year: undefined,
    genre: undefined,
});

const addTagIds = ref<number[]>([]);
const removeTagIds = ref<number[]>([]);

// Computed properties to handle nullable numbers properly
const yearModel = computed({
    get: () => formData.value.year ?? '',
    set: (val: string | number) => {
        if (val === '') {
            formData.value.year = undefined;
        } else {
            const num = Number(val);
            formData.value.year = Number.isNaN(num) ? undefined : num;
        }
    },
});

const hasChanges = computed(() => {
    return (
        formData.value.artist_name !== undefined ||
        formData.value.album_name !== undefined ||
        formData.value.year !== undefined ||
        formData.value.genre !== undefined ||
        addTagIds.value.length > 0 ||
        removeTagIds.value.length > 0
    );
});

async function fetchTags(): Promise<void> {
    tagsLoading.value = true;
    try {
        availableTags.value = await getTags(true);
    } catch (e) {
        console.error('Failed to load tags:', e);
    } finally {
        tagsLoading.value = false;
    }
}

function toggleAddTag(tagId: number): void {
    const index = addTagIds.value.indexOf(tagId);
    if (index === -1) {
        addTagIds.value.push(tagId);
        // Remove from remove list if present
        const removeIndex = removeTagIds.value.indexOf(tagId);
        if (removeIndex !== -1) {
            removeTagIds.value.splice(removeIndex, 1);
        }
    } else {
        addTagIds.value.splice(index, 1);
    }
}

function toggleRemoveTag(tagId: number): void {
    const index = removeTagIds.value.indexOf(tagId);
    if (index === -1) {
        removeTagIds.value.push(tagId);
        // Remove from add list if present
        const addIndex = addTagIds.value.indexOf(tagId);
        if (addIndex !== -1) {
            addTagIds.value.splice(addIndex, 1);
        }
    } else {
        removeTagIds.value.splice(index, 1);
    }
}

function getTagStyle(tag: Tag): Record<string, string> {
    if (!tag.color) return {};
    // Only append alpha for hex colors; other formats get the color directly
    const bgColor = tag.color.startsWith('#')
        ? `${tag.color}20`
        : tag.color;
    return {
        backgroundColor: bgColor,
        borderColor: tag.color,
        color: tag.color,
    };
}

async function handleSubmit(): Promise<void> {
    if (!hasChanges.value || loading.value) return;

    loading.value = true;
    error.value = null;

    try {
        const songIds = props.songs.map((s) => s.id);
        const data: BulkUpdateSongsData = {};

        // Only include fields that have been set
        if (formData.value.artist_name !== undefined) {
            data.artist_name = formData.value.artist_name;
        }
        if (formData.value.album_name !== undefined) {
            data.album_name = formData.value.album_name;
        }
        if (formData.value.year !== undefined) {
            data.year = formData.value.year;
        }
        if (formData.value.genre !== undefined) {
            data.genre = formData.value.genre;
        }
        if (addTagIds.value.length > 0) {
            data.add_tag_ids = addTagIds.value;
        }
        if (removeTagIds.value.length > 0) {
            data.remove_tag_ids = removeTagIds.value;
        }

        const updated = await bulkUpdateSongs(songIds, data);
        emit('updated', updated);
        emit('close');
    } catch (e) {
        error.value = e instanceof Error ? e.message : 'Failed to update songs';
    } finally {
        loading.value = false;
    }
}

onMounted(fetchTags);
</script>

<template>
    <Teleport to="body">
        <div
            class="fixed inset-0 bg-black/50 flex items-center justify-center z-50"
            @click.self="emit('close')"
        >
            <div class="bg-surface-800 rounded-lg w-full max-w-lg max-h-[90vh] flex flex-col">
                <!-- Header -->
                <div class="flex items-center justify-between px-6 py-4 border-b border-surface-700">
                    <h2 class="text-xl font-bold text-surface-50">Bulk Edit</h2>
                    <button
                        @click="emit('close')"
                        class="p-1 text-surface-400 hover:text-surface-50 transition-colors"
                        aria-label="Close"
                    >
                        <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" />
                        </svg>
                    </button>
                </div>

                <!-- Selection info -->
                <div class="px-6 py-3 border-b border-surface-700 bg-surface-900/50">
                    <p class="text-sm text-surface-400">
                        {{ songs.length }} song{{ songs.length === 1 ? '' : 's' }} selected
                    </p>
                    <p class="text-xs text-surface-500 mt-1">
                        Only fields you fill in will be updated
                    </p>
                </div>

                <!-- Error message -->
                <div v-if="error" class="px-6 py-3 bg-red-500/10 border-b border-red-500/20">
                    <p class="text-sm text-red-400">{{ error }}</p>
                </div>

                <!-- Form -->
                <form @submit.prevent="handleSubmit" class="flex-1 overflow-y-auto px-6 py-4">
                    <div class="space-y-4">
                        <!-- Artist -->
                        <div>
                            <label for="bulk-artist" class="block text-sm font-medium text-surface-300 mb-1">
                                Artist
                            </label>
                            <input
                                id="bulk-artist"
                                v-model="formData.artist_name"
                                type="text"
                                placeholder="Leave empty to keep existing"
                                class="w-full px-4 py-2 bg-surface-700 border border-surface-600 rounded-lg text-surface-50 placeholder-surface-400 focus:outline-none focus:ring-2 focus:ring-green-500"
                            />
                        </div>

                        <!-- Album -->
                        <div>
                            <label for="bulk-album" class="block text-sm font-medium text-surface-300 mb-1">
                                Album
                            </label>
                            <input
                                id="bulk-album"
                                v-model="formData.album_name"
                                type="text"
                                placeholder="Leave empty to keep existing"
                                class="w-full px-4 py-2 bg-surface-700 border border-surface-600 rounded-lg text-surface-50 placeholder-surface-400 focus:outline-none focus:ring-2 focus:ring-green-500"
                            />
                        </div>

                        <!-- Year -->
                        <div>
                            <label for="bulk-year" class="block text-sm font-medium text-surface-300 mb-1">
                                Year
                            </label>
                            <input
                                id="bulk-year"
                                v-model="yearModel"
                                type="number"
                                min="1900"
                                :max="new Date().getFullYear() + 1"
                                placeholder="Leave empty to keep existing"
                                class="w-full px-4 py-2 bg-surface-700 border border-surface-600 rounded-lg text-surface-50 placeholder-surface-400 focus:outline-none focus:ring-2 focus:ring-green-500"
                            />
                        </div>

                        <!-- Genre -->
                        <div>
                            <label for="bulk-genre" class="block text-sm font-medium text-surface-300 mb-1">
                                Genre
                            </label>
                            <input
                                id="bulk-genre"
                                v-model="formData.genre"
                                type="text"
                                placeholder="Leave empty to keep existing"
                                class="w-full px-4 py-2 bg-surface-700 border border-surface-600 rounded-lg text-surface-50 placeholder-surface-400 focus:outline-none focus:ring-2 focus:ring-green-500"
                            />
                        </div>

                        <!-- Tags Section -->
                        <div class="border-t border-surface-700 pt-4">
                            <p class="text-sm font-medium text-surface-300 mb-3">Tags</p>

                            <div v-if="tagsLoading" class="text-surface-400 text-sm">
                                Loading tags...
                            </div>

                            <div v-else-if="availableTags.length === 0" class="text-surface-400 text-sm">
                                No tags available
                            </div>

                            <div v-else class="space-y-3">
                                <!-- Add Tags -->
                                <div>
                                    <p class="text-xs text-surface-500 mb-2">Add tags to all selected songs:</p>
                                    <div class="flex flex-wrap gap-2">
                                        <button
                                            v-for="tag in availableTags"
                                            :key="'add-' + tag.id"
                                            type="button"
                                            @click="toggleAddTag(tag.id)"
                                            class="px-2 py-1 text-sm rounded border transition-all"
                                            :class="addTagIds.includes(tag.id)
                                                ? 'ring-2 ring-green-500 ring-offset-1 ring-offset-surface-800'
                                                : 'opacity-70 hover:opacity-100'"
                                            :style="getTagStyle(tag)"
                                        >
                                            <span v-if="addTagIds.includes(tag.id)" class="mr-1">+</span>
                                            {{ tag.name }}
                                        </button>
                                    </div>
                                </div>

                                <!-- Remove Tags -->
                                <div>
                                    <p class="text-xs text-surface-500 mb-2">Remove tags from all selected songs:</p>
                                    <div class="flex flex-wrap gap-2">
                                        <button
                                            v-for="tag in availableTags"
                                            :key="'remove-' + tag.id"
                                            type="button"
                                            @click="toggleRemoveTag(tag.id)"
                                            class="px-2 py-1 text-sm rounded border transition-all"
                                            :class="removeTagIds.includes(tag.id)
                                                ? 'ring-2 ring-red-500 ring-offset-1 ring-offset-surface-800'
                                                : 'opacity-70 hover:opacity-100'"
                                            :style="getTagStyle(tag)"
                                        >
                                            <span v-if="removeTagIds.includes(tag.id)" class="mr-1">-</span>
                                            {{ tag.name }}
                                        </button>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </form>

                <!-- Footer -->
                <div class="px-6 py-4 border-t border-surface-700 flex gap-3">
                    <button
                        type="button"
                        @click="emit('close')"
                        class="flex-1 px-4 py-2 bg-surface-700 hover:bg-surface-600 text-surface-50 rounded-lg transition-colors"
                    >
                        Cancel
                    </button>
                    <button
                        @click="handleSubmit"
                        :disabled="!hasChanges || loading"
                        class="flex-1 px-4 py-2 bg-green-500 hover:bg-green-600 disabled:bg-green-500/50 text-white font-semibold rounded-lg transition-colors"
                    >
                        {{ loading ? 'Saving...' : `Update ${songs.length} Song${songs.length === 1 ? '' : 's'}` }}
                    </button>
                </div>
            </div>
        </div>
    </Teleport>
</template>

<script setup lang="ts">
import { ref, computed, onMounted } from 'vue';
import type { Tag } from '@/types/models';
import { getTags, createTag } from '@/api/tags';

interface Props {
    selectedTagIds: number[];
}

const props = defineProps<Props>();

const emit = defineEmits<{
    'update:selectedTagIds': [tagIds: number[]];
}>();

const availableTags = ref<Tag[]>([]);
const loading = ref(false);
const error = ref<string | null>(null);
const showDropdown = ref(false);
const searchQuery = ref('');
const creatingTag = ref(false);

const selectedTags = computed(() =>
    availableTags.value.filter((tag) => props.selectedTagIds.includes(tag.id))
);

const filteredTags = computed(() => {
    const query = searchQuery.value.toLowerCase().trim();
    if (!query) return availableTags.value;
    return availableTags.value.filter((tag) =>
        tag.name.toLowerCase().includes(query)
    );
});

const canCreateTag = computed(() => {
    const query = searchQuery.value.trim();
    if (!query) return false;
    return !availableTags.value.some(
        (tag) => tag.name.toLowerCase() === query.toLowerCase()
    );
});

async function fetchTags(): Promise<void> {
    loading.value = true;
    error.value = null;
    try {
        availableTags.value = await getTags(true);
    } catch (e) {
        error.value = e instanceof Error ? e.message : 'Failed to load tags';
    } finally {
        loading.value = false;
    }
}

function toggleTag(tag: Tag): void {
    const currentIds = [...props.selectedTagIds];
    const index = currentIds.indexOf(tag.id);

    if (index === -1) {
        currentIds.push(tag.id);
    } else {
        currentIds.splice(index, 1);
    }

    emit('update:selectedTagIds', currentIds);
}

function removeTag(tagId: number): void {
    const currentIds = props.selectedTagIds.filter((id) => id !== tagId);
    emit('update:selectedTagIds', currentIds);
}

async function handleCreateTag(): Promise<void> {
    const name = searchQuery.value.trim();
    if (!name || creatingTag.value) return;

    creatingTag.value = true;
    try {
        const newTag = await createTag({ name });
        availableTags.value.push(newTag);
        emit('update:selectedTagIds', [...props.selectedTagIds, newTag.id]);
        searchQuery.value = '';
    } catch (e) {
        error.value = e instanceof Error ? e.message : 'Failed to create tag';
    } finally {
        creatingTag.value = false;
    }
}

function getTagStyle(tag: Tag): Record<string, string> {
    if (!tag.color) return {};
    // Tag colors are always hex format from the API
    return {
        backgroundColor: `${tag.color}20`,
        borderColor: tag.color,
        color: tag.color,
    };
}

function handleTriggerKeydown(event: KeyboardEvent): void {
    if (event.key === 'Enter' || event.key === ' ') {
        event.preventDefault();
        showDropdown.value = true;
    }
}

function handleEscape(): void {
    showDropdown.value = false;
}

onMounted(fetchTags);
</script>

<template>
    <div class="relative">
        <label class="block text-sm font-medium text-gray-300 mb-1">Tags</label>

        <!-- Selected tags display -->
        <div
            class="min-h-[42px] flex flex-wrap gap-2 p-2 bg-gray-700 border border-gray-600 rounded-lg cursor-text focus:outline-none focus:ring-2 focus:ring-green-500"
            tabindex="0"
            role="button"
            :aria-expanded="showDropdown"
            aria-haspopup="listbox"
            @click="showDropdown = true"
            @keydown="handleTriggerKeydown"
        >
            <span
                v-for="tag in selectedTags"
                :key="tag.id"
                class="inline-flex items-center gap-1 px-2 py-1 text-sm rounded border"
                :style="getTagStyle(tag)"
                :class="{ 'bg-gray-600 border-gray-500 text-gray-200': !tag.color }"
            >
                {{ tag.name }}
                <button
                    type="button"
                    @click.stop="removeTag(tag.id)"
                    class="hover:opacity-70 transition-opacity"
                    aria-label="Remove tag"
                >
                    <svg class="w-3 h-3" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" />
                    </svg>
                </button>
            </span>
            <span v-if="selectedTags.length === 0" class="text-gray-400 text-sm py-1">
                Click to add tags...
            </span>
        </div>

        <!-- Dropdown -->
        <Teleport to="body">
            <div
                v-if="showDropdown"
                class="fixed inset-0 z-40"
                @click="showDropdown = false"
            />
        </Teleport>
        <div
            v-if="showDropdown"
            class="absolute z-50 w-full mt-1 bg-gray-700 border border-gray-600 rounded-lg shadow-lg overflow-hidden"
        >
            <!-- Search input -->
            <div class="p-2 border-b border-gray-600">
                <input
                    v-model="searchQuery"
                    type="text"
                    placeholder="Search or create tag..."
                    class="w-full px-3 py-2 bg-gray-800 border border-gray-600 rounded text-white placeholder-gray-400 focus:outline-none focus:ring-2 focus:ring-green-500 text-sm"
                    @keydown.enter.prevent="canCreateTag && handleCreateTag()"
                    @keydown.escape="handleEscape"
                />
            </div>

            <!-- Loading state -->
            <div v-if="loading" class="p-4 text-center text-gray-400">
                Loading tags...
            </div>

            <!-- Error state -->
            <div v-else-if="error" class="p-4 text-center text-red-400 text-sm">
                {{ error }}
            </div>

            <!-- Tags list -->
            <div v-else class="max-h-48 overflow-y-auto">
                <button
                    v-for="tag in filteredTags"
                    :key="tag.id"
                    type="button"
                    @click="toggleTag(tag)"
                    class="w-full px-4 py-2 text-left text-sm hover:bg-gray-600 transition-colors flex items-center justify-between"
                    :class="selectedTagIds.includes(tag.id) ? 'text-green-400' : 'text-gray-200'"
                >
                    <span class="flex items-center gap-2">
                        <span
                            v-if="tag.color"
                            class="w-3 h-3 rounded-full"
                            :style="{ backgroundColor: tag.color }"
                        />
                        {{ tag.name }}
                    </span>
                    <svg
                        v-if="selectedTagIds.includes(tag.id)"
                        class="w-4 h-4"
                        fill="currentColor"
                        viewBox="0 0 24 24"
                    >
                        <path d="M9 16.17L4.83 12l-1.42 1.41L9 19 21 7l-1.41-1.41z" />
                    </svg>
                </button>

                <!-- Create new tag option -->
                <button
                    v-if="canCreateTag"
                    type="button"
                    @click="handleCreateTag"
                    :disabled="creatingTag"
                    class="w-full px-4 py-2 text-left text-sm text-green-400 hover:bg-gray-600 transition-colors flex items-center gap-2 border-t border-gray-600"
                >
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4" />
                    </svg>
                    {{ creatingTag ? 'Creating...' : `Create "${searchQuery.trim()}"` }}
                </button>

                <!-- Empty state -->
                <div
                    v-if="filteredTags.length === 0 && !canCreateTag"
                    class="p-4 text-center text-gray-400 text-sm"
                >
                    No tags found
                </div>
            </div>
        </div>
    </div>
</template>

<script setup lang="ts">
import { RouterLink } from 'vue-router';
import { usePlayer } from '@/composables';

interface Props {
    showCover?: boolean;
    coverSize?: 'sm' | 'md' | 'lg';
}

withDefaults(defineProps<Props>(), {
    showCover: true,
    coverSize: 'md',
});

const { currentSong } = usePlayer();

const coverSizeClasses: Record<string, string> = {
    sm: 'w-10 h-10',
    md: 'w-12 h-12',
    lg: 'w-14 h-14',
};
</script>

<template>
    <div class="flex items-center gap-3">
        <template v-if="currentSong">
            <!-- Album cover -->
            <div
                v-if="showCover"
                :class="[coverSizeClasses[coverSize], 'bg-gray-700 rounded flex-shrink-0 overflow-hidden']"
            >
                <img
                    v-if="currentSong.album?.cover_url"
                    :src="currentSong.album.cover_url"
                    :alt="currentSong.album.title"
                    class="w-full h-full object-cover"
                />
                <div v-else class="w-full h-full flex items-center justify-center">
                    <svg class="w-6 h-6 text-gray-600" fill="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                        <path d="M12 3v10.55c-.59-.34-1.27-.55-2-.55-2.21 0-4 1.79-4 4s1.79 4 4 4 4-1.79 4-4V7h4V3h-6z" />
                    </svg>
                </div>
            </div>

            <!-- Song info -->
            <div class="min-w-0">
                <p class="text-sm text-white truncate font-medium">
                    {{ currentSong.title }}
                </p>
                <p class="text-xs text-gray-400 truncate">
                    <RouterLink
                        v-if="currentSong.artist"
                        :to="{ name: 'artist-detail', params: { slug: currentSong.artist.slug } }"
                        class="hover:underline hover:text-white transition-colors"
                    >
                        {{ currentSong.artist.name }}
                    </RouterLink>
                    <span v-else>Unknown Artist</span>
                </p>
            </div>
        </template>

        <template v-else>
            <div
                v-if="showCover"
                :class="[coverSizeClasses[coverSize], 'bg-gray-700 rounded flex-shrink-0']"
            />
            <div class="min-w-0">
                <p class="text-sm text-gray-500">No song playing</p>
            </div>
        </template>
    </div>
</template>

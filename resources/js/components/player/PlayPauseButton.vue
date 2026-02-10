<script setup lang="ts">
import { computed } from 'vue';
import { usePlayer } from '@/composables';

interface Props {
    size?: 'sm' | 'md' | 'lg';
    variant?: 'default' | 'primary';
    disabled?: boolean;
}

const props = withDefaults(defineProps<Props>(), {
    size: 'md',
    variant: 'default',
    disabled: false,
});

const { isPlaying, currentSong, togglePlayPause } = usePlayer();

const isDisabled = computed(() => props.disabled || !currentSong.value);

const sizeClasses = computed(() => {
    switch (props.size) {
        case 'sm':
            return 'w-8 h-8';
        case 'lg':
            return 'w-14 h-14';
        default:
            return 'w-10 h-10';
    }
});

const iconSizeClasses = computed(() => {
    switch (props.size) {
        case 'sm':
            return 'w-4 h-4';
        case 'lg':
            return 'w-6 h-6';
        default:
            return 'w-5 h-5';
    }
});

const variantClasses = computed(() => {
    switch (props.variant) {
        case 'primary':
            return 'bg-white text-gray-900 hover:scale-105';
        default:
            return 'bg-gray-700 text-white hover:bg-gray-600';
    }
});
</script>

<template>
    <button
        @click="togglePlayPause"
        :disabled="isDisabled"
        :class="[
            sizeClasses,
            variantClasses,
            'rounded-full flex items-center justify-center transition-all',
            'disabled:opacity-50 disabled:cursor-not-allowed disabled:hover:scale-100',
        ]"
        :aria-label="isPlaying ? 'Pause' : 'Play'"
    >
        <svg
            v-if="isPlaying"
            :class="iconSizeClasses"
            fill="currentColor"
            viewBox="0 0 24 24"
            aria-hidden="true"
        >
            <path d="M6 19h4V5H6v14zm8-14v14h4V5h-4z" />
        </svg>
        <svg
            v-else
            :class="[iconSizeClasses, 'ml-0.5']"
            fill="currentColor"
            viewBox="0 0 24 24"
            aria-hidden="true"
        >
            <path d="M8 5v14l11-7z" />
        </svg>
    </button>
</template>

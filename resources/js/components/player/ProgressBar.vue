<script setup lang="ts">
import { ref, computed } from 'vue';
import { usePlayer } from '@/composables';

interface Props {
    showTime?: boolean;
    showTimeRemaining?: boolean;
}

const props = withDefaults(defineProps<Props>(), {
    showTime: true,
    showTimeRemaining: false,
});

const { currentTime, duration, progress, formattedCurrentTime, formattedDuration, formattedTimeRemaining, seek } =
    usePlayer();

const isDragging = ref(false);
const hoverProgress = ref(0);

const displayedTime = computed(() => {
    if (props.showTimeRemaining) {
        return formattedTimeRemaining.value;
    }
    return formattedDuration.value;
});

function handleProgressClick(event: MouseEvent): void {
    const target = event.currentTarget as HTMLElement;
    const rect = target.getBoundingClientRect();
    const rawPercent = ((event.clientX - rect.left) / rect.width) * 100;
    const percent = Math.max(0, Math.min(100, rawPercent));
    const time = (percent / 100) * duration.value;
    seek(Math.max(0, Math.min(duration.value, time)));
}

function handleMouseMove(event: MouseEvent): void {
    const target = event.currentTarget as HTMLElement;
    const rect = target.getBoundingClientRect();
    const rawPercent = ((event.clientX - rect.left) / rect.width) * 100;
    hoverProgress.value = Math.max(0, Math.min(100, rawPercent));
}

function handleKeyDown(event: KeyboardEvent): void {
    const step = 5; // 5 seconds
    let newTime = currentTime.value;

    switch (event.key) {
        case 'ArrowLeft':
            newTime = Math.max(0, currentTime.value - step);
            break;
        case 'ArrowRight':
            newTime = Math.min(duration.value, currentTime.value + step);
            break;
        case 'Home':
            newTime = 0;
            break;
        case 'End':
            newTime = duration.value;
            break;
        default:
            return;
    }

    event.preventDefault();
    seek(newTime);
}

function handleMouseDown(): void {
    isDragging.value = true;
}

function handleMouseUp(): void {
    isDragging.value = false;
}
</script>

<template>
    <div class="flex items-center gap-2 w-full">
        <span v-if="showTime" class="text-xs text-gray-400 w-10 text-right tabular-nums">
            {{ formattedCurrentTime }}
        </span>
        <div
            class="flex-1 h-1 bg-gray-600 rounded-full cursor-pointer group relative"
            @click="handleProgressClick"
            @mousemove="handleMouseMove"
            @mousedown="handleMouseDown"
            @mouseup="handleMouseUp"
            @mouseleave="handleMouseUp"
            @keydown="handleKeyDown"
            role="slider"
            tabindex="0"
            :aria-valuenow="currentTime"
            :aria-valuemin="0"
            :aria-valuemax="duration"
            aria-label="Seek"
        >
            <!-- Progress fill -->
            <div
                class="h-full bg-white rounded-full group-hover:bg-green-500 transition-colors relative"
                :style="{ width: `${progress}%` }"
            >
                <!-- Thumb -->
                <div
                    class="absolute right-0 top-1/2 -translate-y-1/2 w-3 h-3 bg-white rounded-full opacity-0 group-hover:opacity-100 transition-opacity shadow-md"
                />
            </div>
        </div>
        <span v-if="showTime" class="text-xs text-gray-400 w-10 tabular-nums">
            {{ displayedTime }}
        </span>
    </div>
</template>

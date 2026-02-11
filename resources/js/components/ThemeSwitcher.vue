<script setup lang="ts">
import { useThemeStore } from '@/stores/theme';
import { computed, ref, onMounted, onUnmounted } from 'vue';

const themeStore = useThemeStore();
const isOpen = ref(false);

const currentThemeIcon = computed(() => {
    switch (themeStore.currentTheme) {
        case 'light': return '☀️';
        case 'dark': return '🌙';
        case 'neon': return '🟣';
        case 'retro': return '📺';
        default: return '🎨';
    }
});

function toggleDropdown() {
    isOpen.value = !isOpen.value;
}

function selectTheme(theme: string) {
    themeStore.setTheme(theme as any);
    isOpen.value = false;
}

// Close dropdown when clicking outside
function handleClickOutside(event: MouseEvent) {
    const target = event.target as HTMLElement;
    if (!target.closest('.theme-switcher')) {
        isOpen.value = false;
    }
}

onMounted(() => {
    document.addEventListener('click', handleClickOutside);
});

onUnmounted(() => {
    document.removeEventListener('click', handleClickOutside);
});
</script>

<template>
    <div class="relative theme-switcher">
        <button 
            @click.stop="toggleDropdown"
            class="flex items-center gap-2 px-3 py-2 rounded-lg bg-surface-700 hover:bg-surface-600 text-surface-200 transition-colors"
            title="Switch Theme"
        >
            <span>{{ currentThemeIcon }}</span>
            <span class="capitalize hidden sm:block">{{ themeStore.currentTheme }}</span>
            <svg 
                xmlns="http://www.w3.org/2000/svg" 
                viewBox="0 0 20 20" 
                fill="currentColor" 
                class="w-4 h-4 transition-transform duration-200"
                :class="{ 'rotate-180': isOpen }"
            >
                <path fill-rule="evenodd" d="M5.23 7.21a.75.75 0 011.06.02L10 11.168l3.71-3.938a.75.75 0 111.08 1.04l-4.25 4.5a.75.75 0 01-1.08 0l-4.25-4.5a.75.75 0 01.02-1.06z" clip-rule="evenodd" />
            </svg>
        </button>

        <div 
            v-if="isOpen"
            class="absolute right-0 mt-2 w-40 bg-surface-800 border border-surface-700 rounded-lg shadow-xl z-50 overflow-hidden"
        >
            <div class="py-1">
                <button
                    v-for="theme in themeStore.availableThemes"
                    :key="theme"
                    @click="selectTheme(theme)"
                    class="w-full text-left px-4 py-2 text-sm text-surface-300 hover:bg-surface-700 hover:text-white transition-colors flex items-center gap-2"
                    :class="{ 'text-primary-400 bg-surface-700/50': themeStore.currentTheme === theme }"
                >
                    <span class="w-4">
                        {{ theme === themeStore.currentTheme ? '✓' : '' }}
                    </span>
                    <span class="capitalize">{{ theme }}</span>
                </button>
            </div>
        </div>
    </div>
</template>

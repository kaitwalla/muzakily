import { defineStore } from 'pinia';
import { ref, watchEffect } from 'vue';

export type Theme = 'light' | 'dark' | 'neon' | 'retro';

export const useThemeStore = defineStore('theme', () => {
    // Initialize from localStorage or default to 'dark'
    const currentTheme = ref<Theme>((localStorage.getItem('theme') as Theme) || 'dark');

    const availableThemes: Theme[] = ['light', 'dark', 'neon', 'retro'];

    // Apply theme class to document element
    watchEffect(() => {
        const root = document.documentElement;

        // Remove all theme classes
        root.classList.remove('dark', 'theme-light', 'theme-neon', 'theme-retro');

        // Apply theme-specific classes
        switch (currentTheme.value) {
            case 'light':
                root.classList.add('theme-light');
                break;
            case 'dark':
                root.classList.add('dark');
                break;
            case 'neon':
                root.classList.add('dark', 'theme-neon');
                break;
            case 'retro':
                root.classList.add('theme-retro');
                break;
        }

        // Persist
        localStorage.setItem('theme', currentTheme.value);
    });

    function setTheme(theme: Theme) {
        if (availableThemes.includes(theme)) {
            currentTheme.value = theme;
        }
    }

    return {
        currentTheme,
        availableThemes,
        setTheme,
    };
});

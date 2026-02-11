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
        
        // Remove all previous theme classes
        availableThemes.forEach(theme => {
            root.classList.remove(`theme-${theme}`);
            if (theme === 'dark') root.classList.remove('dark');
        });

        // Apply new theme class
        if (currentTheme.value === 'light') {
            // Light theme is default, no class needed (or explicit light class if configured)
            // But we might want to ensure 'dark' is removed
            root.classList.remove('dark');
        } else if (currentTheme.value === 'dark') {
            root.classList.add('dark');
        } else {
            // For custom themes, we might want to also add 'dark' if they are dark-based, 
            // but for now let's assume they handle their own variables.
            // Based on the CSS, neon and retro define their own surfaces.
            root.classList.add(`theme-${currentTheme.value}`);
            
            // Should custom themes also set 'dark' for tailwind dark: modifiers?
            // The plan implies these are standalone themes. 
            // If they are dark-based custom themes, adding 'dark' might be beneficial for 
            // components relying on dark: variant. 
            // Let's assume 'neon' is dark-based and 'retro' is warm/light-ish but defined as its own.
            // Actually, simply setting the theme class repeats the vars. 
            // If the user wants dark: modifiers to work, we should toggle 'dark' class based on the theme brightness.
            if (currentTheme.value === 'neon') {
                 root.classList.add('dark');
            }
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

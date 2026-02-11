import { describe, it, expect, beforeEach } from 'vitest';
import { setActivePinia, createPinia } from 'pinia';
import { useThemeStore } from '../../resources/js/stores/theme';

describe('useThemeStore', () => {
    beforeEach(() => {
        setActivePinia(createPinia());
        // Reset local storage
        localStorage.clear();
        // Reset document classes
        document.documentElement.className = '';
    });

    it('initializes with default theme if no localStorage', () => {
        const store = useThemeStore();
        expect(store.currentTheme).toBe('dark');
        expect(document.documentElement.classList.contains('dark')).toBe(true);
    });

    it('switches theme correctly', async () => {
        const store = useThemeStore();

        store.setTheme('light');
        // wait for watchEffect
        await new Promise(resolve => setTimeout(resolve, 0));

        expect(store.currentTheme).toBe('light');
        expect(document.documentElement.classList.contains('dark')).toBe(false);
        expect(localStorage.getItem('theme')).toBe('light');

        store.setTheme('neon');
        await new Promise(resolve => setTimeout(resolve, 0));

        expect(store.currentTheme).toBe('neon');
        expect(document.documentElement.classList.contains('theme-neon')).toBe(true);
        // Neon is a dark theme, so it should also have 'dark' class
        expect(document.documentElement.classList.contains('dark')).toBe(true);
    });

    it('loads theme from localStorage', () => {
        localStorage.setItem('theme', 'retro');
        const store = useThemeStore();

        expect(store.currentTheme).toBe('retro');
        // Retro is a light/warm theme, so it shouldn't have 'dark' unless specified
        // Based on my implementation logic:
        // if (currentTheme.value === 'light') remove dark
        // else if (currentTheme.value === 'dark') add dark
        // else add theme-{name} AND if neon add dark

        // So retro should have theme-retro but NOT dark
        expect(document.documentElement.classList.contains('theme-retro')).toBe(true);
        expect(document.documentElement.classList.contains('dark')).toBe(false);
    });
});

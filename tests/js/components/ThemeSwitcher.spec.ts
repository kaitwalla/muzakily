import { describe, it, expect, beforeEach, vi } from 'vitest';
import { mount } from '@vue/test-utils';
import { createPinia, setActivePinia } from 'pinia';
import ThemeSwitcher from '@/components/ThemeSwitcher.vue';
import { useThemeStore } from '@/stores/theme';

describe('ThemeSwitcher', () => {
    beforeEach(() => {
        setActivePinia(createPinia());
        localStorage.clear();
        document.documentElement.className = '';
    });

    it('should render the theme switcher button', () => {
        const wrapper = mount(ThemeSwitcher);
        expect(wrapper.find('button').exists()).toBe(true);
    });

    it('should display current theme name', () => {
        const wrapper = mount(ThemeSwitcher);
        expect(wrapper.text()).toContain('dark');
    });

    it('should display correct icon for dark theme', () => {
        const wrapper = mount(ThemeSwitcher);
        expect(wrapper.text()).toContain('🌙');
    });

    it('should display correct icon for light theme', async () => {
        const store = useThemeStore();
        store.setTheme('light');

        const wrapper = mount(ThemeSwitcher);
        expect(wrapper.text()).toContain('☀️');
    });

    it('should display correct icon for neon theme', async () => {
        const store = useThemeStore();
        store.setTheme('neon');

        const wrapper = mount(ThemeSwitcher);
        expect(wrapper.text()).toContain('🟣');
    });

    it('should display correct icon for retro theme', async () => {
        const store = useThemeStore();
        store.setTheme('retro');

        const wrapper = mount(ThemeSwitcher);
        expect(wrapper.text()).toContain('📺');
    });

    it('should toggle dropdown when button is clicked', async () => {
        const wrapper = mount(ThemeSwitcher);

        // Dropdown should be closed initially
        expect(wrapper.find('.absolute').exists()).toBe(false);

        // Click to open
        await wrapper.find('button').trigger('click');
        expect(wrapper.find('.absolute').exists()).toBe(true);
    });

    it('should display all available themes in dropdown', async () => {
        const wrapper = mount(ThemeSwitcher);
        await wrapper.find('button').trigger('click');

        const dropdown = wrapper.find('.absolute');
        expect(dropdown.text()).toContain('light');
        expect(dropdown.text()).toContain('dark');
        expect(dropdown.text()).toContain('neon');
        expect(dropdown.text()).toContain('retro');
    });

    it('should change theme when theme option is clicked', async () => {
        const store = useThemeStore();
        const wrapper = mount(ThemeSwitcher);

        // Open dropdown
        await wrapper.find('button').trigger('click');

        // Find and click light theme button
        const themeButtons = wrapper.findAll('.absolute button');
        const lightButton = themeButtons.find((btn) => btn.text().includes('light'));
        expect(lightButton).toBeDefined();
        await lightButton?.trigger('click');

        expect(store.currentTheme).toBe('light');
    });

    it('should close dropdown after selecting theme', async () => {
        const wrapper = mount(ThemeSwitcher);

        // Open dropdown
        await wrapper.find('button').trigger('click');
        expect(wrapper.find('.absolute').exists()).toBe(true);

        // Select a theme
        const themeButtons = wrapper.findAll('.absolute button');
        await themeButtons[0].trigger('click');

        // Dropdown should be closed
        expect(wrapper.find('.absolute').exists()).toBe(false);
    });

    it('should show checkmark for current theme', async () => {
        const store = useThemeStore();
        store.setTheme('neon');

        const wrapper = mount(ThemeSwitcher);
        await wrapper.find('button').trigger('click');

        const themeButtons = wrapper.findAll('.absolute button');
        const neonButton = themeButtons.find((btn) => btn.text().includes('neon'));
        expect(neonButton).toBeDefined();
        expect(neonButton?.text()).toContain('✓');
    });

    it('should rotate chevron icon when dropdown is open', async () => {
        const wrapper = mount(ThemeSwitcher);
        const chevron = wrapper.find('svg');

        expect(chevron.classes()).not.toContain('rotate-180');

        await wrapper.find('button').trigger('click');
        expect(chevron.classes()).toContain('rotate-180');
    });
});

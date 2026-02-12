import { describe, it, expect, beforeEach, vi } from 'vitest';
import { mount, flushPromises } from '@vue/test-utils';
import { createPinia, setActivePinia } from 'pinia';
import AppSidebar from '@/components/AppSidebar.vue';
import { usePlaylistsStore } from '@/stores/playlists';
import { useAuthStore } from '@/stores/auth';
import { createMockPlaylist } from '../utils/test-helpers';

describe('AppSidebar', () => {
    beforeEach(() => {
        setActivePinia(createPinia());
    });

    it('should render the sidebar', () => {
        const wrapper = mount(AppSidebar);
        expect(wrapper.find('aside').exists()).toBe(true);
    });

    it('should display navigation links', () => {
        const wrapper = mount(AppSidebar);
        expect(wrapper.text()).toContain('Home');
        expect(wrapper.text()).toContain('Search');
    });

    it('should display library section', () => {
        const wrapper = mount(AppSidebar);
        expect(wrapper.text()).toContain('Library');
        expect(wrapper.text()).toContain('Songs');
        expect(wrapper.text()).toContain('Albums');
        expect(wrapper.text()).toContain('Artists');
        expect(wrapper.text()).toContain('Playlists');
        expect(wrapper.text()).toContain('Upload');
    });

    it('should display "Your Playlists" section', () => {
        const wrapper = mount(AppSidebar);
        expect(wrapper.text()).toContain('Your Playlists');
    });

    it('should show "No playlists yet" when no playlists exist', () => {
        const wrapper = mount(AppSidebar);
        expect(wrapper.text()).toContain('No playlists yet');
    });

    it('should display user playlists when available', () => {
        const playlistsStore = usePlaylistsStore();
        playlistsStore.playlists = [
            createMockPlaylist({ id: 1, name: 'My First Playlist' }),
            createMockPlaylist({ id: 2, name: 'Workout Mix' }),
        ];

        const wrapper = mount(AppSidebar);
        expect(wrapper.text()).toContain('My First Playlist');
        expect(wrapper.text()).toContain('Workout Mix');
    });

    it('should limit displayed playlists to 10', () => {
        const playlistsStore = usePlaylistsStore();
        playlistsStore.playlists = Array.from({ length: 15 }, (_, i) =>
            createMockPlaylist({ id: i + 1, name: `Playlist ${i + 1}` })
        );

        const wrapper = mount(AppSidebar);
        // Count playlist links (excluding navigation links)
        const playlistSection = wrapper.find('.overflow-y-auto');
        const playlistLinks = playlistSection.findAll('a');
        expect(playlistLinks).toHaveLength(10);
    });

    it('should have correct routes for navigation links', () => {
        const wrapper = mount(AppSidebar, {
            global: {
                stubs: {
                    RouterLink: {
                        template: '<a :data-to="to"><slot /></a>',
                        props: ['to'],
                    },
                },
            },
        });
        const links = wrapper.findAll('a[data-to]');
        const routes = links.map((link) => link.attributes('data-to'));

        expect(routes).toContain('/');
        expect(routes).toContain('/search');
        expect(routes).toContain('/songs');
        expect(routes).toContain('/albums');
        expect(routes).toContain('/artists');
        expect(routes).toContain('/playlists');
        expect(routes).toContain('/upload');
    });

    it('should fetch playlists on mount when authenticated', async () => {
        const authStore = useAuthStore();
        const playlistsStore = usePlaylistsStore();

        authStore.initialized = true;
        authStore.user = {
            id: 1,
            name: 'Test User',
            email: 'test@example.com',
            role: 'user',
            preferences: { theme: 'dark', default_view: 'grid' },
            created_at: '2024-01-01T00:00:00Z',
            updated_at: '2024-01-01T00:00:00Z',
        };

        const fetchSpy = vi.spyOn(playlistsStore, 'fetchPlaylists').mockResolvedValue();

        mount(AppSidebar);
        await flushPromises();

        expect(fetchSpy).toHaveBeenCalled();
    });

    it('should not fetch playlists when not authenticated', () => {
        const authStore = useAuthStore();
        const playlistsStore = usePlaylistsStore();

        authStore.initialized = true;
        authStore.user = null;

        const fetchSpy = vi.spyOn(playlistsStore, 'fetchPlaylists');

        mount(AppSidebar);

        expect(fetchSpy).not.toHaveBeenCalled();
    });

    it('should not fetch playlists when already loaded', () => {
        const authStore = useAuthStore();
        const playlistsStore = usePlaylistsStore();

        authStore.initialized = true;
        authStore.user = {
            id: 1,
            name: 'Test User',
            email: 'test@example.com',
            role: 'user',
            preferences: { theme: 'dark', default_view: 'grid' },
            created_at: '2024-01-01T00:00:00Z',
            updated_at: '2024-01-01T00:00:00Z',
        };
        playlistsStore.playlists = [createMockPlaylist()];

        const fetchSpy = vi.spyOn(playlistsStore, 'fetchPlaylists');

        mount(AppSidebar);

        expect(fetchSpy).not.toHaveBeenCalled();
    });
});

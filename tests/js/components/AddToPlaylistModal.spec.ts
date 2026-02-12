import { describe, it, expect, beforeEach, vi } from 'vitest';
import { mount } from '@vue/test-utils';
import { createPinia, setActivePinia } from 'pinia';
import AddToPlaylistModal from '@/components/playlist/AddToPlaylistModal.vue';
import { usePlaylistsStore } from '@/stores/playlists';
import { createMockSong, createMockPlaylist } from '../utils/test-helpers';

describe('AddToPlaylistModal', () => {
    beforeEach(() => {
        setActivePinia(createPinia());
    });

    const createWrapper = (songsOverride = [createMockSong()]) => {
        return mount(AddToPlaylistModal, {
            props: { songs: songsOverride },
        });
    };

    it('should render the modal', () => {
        const wrapper = createWrapper();
        expect(wrapper.find('.fixed').exists()).toBe(true);
    });

    it('should display modal title', () => {
        const wrapper = createWrapper();
        expect(wrapper.text()).toContain('Add to playlist');
    });

    it('should display selected song count', () => {
        const wrapper = createWrapper([createMockSong(), createMockSong()]);
        expect(wrapper.text()).toContain('2 songs selected');
    });

    it('should use singular for 1 song', () => {
        const wrapper = createWrapper([createMockSong()]);
        expect(wrapper.text()).toContain('1 song selected');
    });

    it('should have close button', () => {
        const wrapper = createWrapper();
        const closeButton = wrapper.find('[aria-label="Close"]');
        expect(closeButton.exists()).toBe(true);
    });

    it('should emit close when close button is clicked', async () => {
        const wrapper = createWrapper();
        const closeButton = wrapper.find('[aria-label="Close"]');
        await closeButton.trigger('click');

        expect(wrapper.emitted('close')).toHaveLength(1);
    });

    it('should emit close when clicking backdrop', async () => {
        const wrapper = createWrapper();
        await wrapper.find('.fixed.inset-0').trigger('click');

        expect(wrapper.emitted('close')).toHaveLength(1);
    });

    it('should show create new playlist button initially', () => {
        const wrapper = createWrapper();
        expect(wrapper.text()).toContain('Create new playlist');
    });

    it('should show create form when create button is clicked', async () => {
        const wrapper = createWrapper();
        // Find the create new playlist button by its text
        const buttons = wrapper.findAll('button');
        const createButton = buttons.find((btn) => btn.text().includes('Create new playlist'));
        await createButton!.trigger('click');

        expect(wrapper.find('input[placeholder="Playlist name"]').exists()).toBe(true);
    });

    it('should hide create form when cancel is clicked', async () => {
        const wrapper = createWrapper();

        // Show form
        const buttons = wrapper.findAll('button');
        const createButton = buttons.find((btn) => btn.text().includes('Create new playlist'));
        await createButton!.trigger('click');
        expect(wrapper.find('input[placeholder="Playlist name"]').exists()).toBe(true);

        // Click cancel
        const allButtons = wrapper.findAll('button');
        const cancelButton = allButtons.find((btn) => btn.text() === 'Cancel');
        await cancelButton!.trigger('click');

        expect(wrapper.find('input[placeholder="Playlist name"]').exists()).toBe(false);
    });

    it('should show "No playlists yet" when no playlists exist', () => {
        const wrapper = createWrapper();
        expect(wrapper.text()).toContain('No playlists yet');
        expect(wrapper.text()).toContain('Create one above to get started');
    });

    it('should display existing playlists', async () => {
        const store = usePlaylistsStore();
        store.playlists = [
            createMockPlaylist({ id: 1, name: 'Playlist One' }),
            createMockPlaylist({ id: 2, name: 'Playlist Two' }),
        ];

        const wrapper = createWrapper();
        expect(wrapper.text()).toContain('Playlist One');
        expect(wrapper.text()).toContain('Playlist Two');
    });

    it('should add songs to playlist when clicked', async () => {
        const store = usePlaylistsStore();
        const addSpy = vi.spyOn(store, 'addSongsToPlaylist').mockResolvedValue();
        store.playlists = [createMockPlaylist({ id: 1, name: 'My Playlist' })];

        const songs = [createMockSong({ id: 'song-1' }), createMockSong({ id: 'song-2' })];
        const wrapper = mount(AddToPlaylistModal, {
            props: { songs },
        });

        const playlistButton = wrapper.find('.overflow-y-auto button');
        await playlistButton.trigger('click');

        expect(addSpy).toHaveBeenCalledWith(1, ['song-1', 'song-2']);
    });

    it('should emit added and close after adding to playlist', async () => {
        const store = usePlaylistsStore();
        vi.spyOn(store, 'addSongsToPlaylist').mockResolvedValue();
        const playlist = createMockPlaylist({ id: 1, name: 'My Playlist' });
        store.playlists = [playlist];

        const wrapper = createWrapper();

        const playlistButton = wrapper.find('.overflow-y-auto button');
        await playlistButton.trigger('click');

        // Wait for async operation
        await new Promise((resolve) => setTimeout(resolve, 0));

        expect(wrapper.emitted('added')).toBeDefined();
        expect(wrapper.emitted('close')).toBeDefined();
    });

    it('should show error message on failure', async () => {
        const store = usePlaylistsStore();
        vi.spyOn(store, 'addSongsToPlaylist').mockRejectedValue(new Error('Failed'));
        store.playlists = [createMockPlaylist({ id: 1, name: 'My Playlist' })];

        const wrapper = createWrapper();

        const playlistButton = wrapper.find('.overflow-y-auto button');
        await playlistButton.trigger('click');

        // Wait for async operation
        await new Promise((resolve) => setTimeout(resolve, 0));

        expect(wrapper.text()).toContain('Failed to add songs to playlist');
    });

    it('should create playlist and add songs', async () => {
        const store = usePlaylistsStore();
        const newPlaylist = createMockPlaylist({ id: 99, name: 'New Playlist' });
        vi.spyOn(store, 'createPlaylist').mockResolvedValue(newPlaylist);
        vi.spyOn(store, 'addSongsToPlaylist').mockResolvedValue();

        const wrapper = createWrapper([createMockSong({ id: 'song-1' })]);

        // Show create form
        const buttons = wrapper.findAll('button');
        const createButton = buttons.find((btn) => btn.text().includes('Create new playlist'));
        await createButton!.trigger('click');

        // Enter playlist name
        const input = wrapper.find('input[placeholder="Playlist name"]');
        await input.setValue('My New Playlist');

        // Submit form
        const form = wrapper.find('form');
        await form.trigger('submit.prevent');

        // Wait for async operations
        await new Promise((resolve) => setTimeout(resolve, 0));

        expect(store.createPlaylist).toHaveBeenCalledWith({ name: 'My New Playlist' });
        expect(store.addSongsToPlaylist).toHaveBeenCalledWith(99, ['song-1']);
    });

    it('should display playlist cover when available', async () => {
        const store = usePlaylistsStore();
        store.playlists = [
            createMockPlaylist({
                id: 1,
                name: 'My Playlist',
                cover_url: 'https://example.com/cover.jpg',
            }),
        ];

        const wrapper = createWrapper();
        const img = wrapper.find('.overflow-y-auto img');
        expect(img.exists()).toBe(true);
        expect(img.attributes('src')).toBe('https://example.com/cover.jpg');
    });

    it('should show placeholder when no playlist cover', async () => {
        const store = usePlaylistsStore();
        store.playlists = [
            createMockPlaylist({ id: 1, name: 'My Playlist', cover_url: null }),
        ];

        const wrapper = createWrapper();
        const img = wrapper.find('.overflow-y-auto img');
        expect(img.exists()).toBe(false);
        // Should have placeholder SVG
        expect(wrapper.find('.overflow-y-auto svg').exists()).toBe(true);
    });

    it('should disable create button when name is empty', async () => {
        const wrapper = createWrapper();

        // Show create form
        const buttons = wrapper.findAll('button');
        const createButton = buttons.find((btn) => btn.text().includes('Create new playlist'));
        await createButton!.trigger('click');

        const submitButton = wrapper.find('button[type="submit"]');
        expect(submitButton.attributes('disabled')).toBeDefined();
    });

    it('should enable create button when name is entered', async () => {
        const wrapper = createWrapper();

        // Show create form
        const buttons = wrapper.findAll('button');
        const createButton = buttons.find((btn) => btn.text().includes('Create new playlist'));
        await createButton!.trigger('click');

        // Enter name
        const input = wrapper.find('input[placeholder="Playlist name"]');
        await input.setValue('Test');

        const submitButton = wrapper.find('button[type="submit"]');
        expect(submitButton.attributes('disabled')).toBeUndefined();
    });
});

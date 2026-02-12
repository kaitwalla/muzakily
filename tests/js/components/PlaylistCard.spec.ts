import { describe, it, expect, beforeEach, vi } from 'vitest';
import { mount } from '@vue/test-utils';
import { createPinia, setActivePinia } from 'pinia';
import PlaylistCard from '@/components/playlist/PlaylistCard.vue';
import { usePlayerStore } from '@/stores/player';
import { createMockPlaylist, createMockSong } from '../utils/test-helpers';

describe('PlaylistCard', () => {
    beforeEach(() => {
        setActivePinia(createPinia());
    });

    const createWrapper = (playlistOverrides = {}) => {
        const playlist = createMockPlaylist(playlistOverrides);
        return mount(PlaylistCard, {
            props: { playlist },
            global: {
                stubs: {
                    RouterLink: {
                        template: '<a :data-to="JSON.stringify(to)"><slot /></a>',
                        props: ['to'],
                    },
                },
            },
        });
    };

    it('should render the playlist card', () => {
        const wrapper = createWrapper();
        expect(wrapper.exists()).toBe(true);
    });

    it('should display playlist name', () => {
        const wrapper = createWrapper({ name: 'My Awesome Playlist' });
        expect(wrapper.text()).toContain('My Awesome Playlist');
    });

    it('should display playlist description when available', () => {
        const wrapper = createWrapper({ description: 'A cool collection of songs' });
        expect(wrapper.text()).toContain('A cool collection of songs');
    });

    it('should display song count when no description', () => {
        const wrapper = createWrapper({ description: null, songs_count: 15 });
        expect(wrapper.text()).toContain('15 songs');
    });

    it('should use singular "song" for count of 1', () => {
        const wrapper = createWrapper({ description: null, songs_count: 1 });
        expect(wrapper.text()).toContain('1 song');
        expect(wrapper.text()).not.toContain('songs');
    });

    it('should display cover image when available', () => {
        const wrapper = createWrapper({ cover_url: 'https://example.com/cover.jpg' });
        const img = wrapper.find('img');
        expect(img.exists()).toBe(true);
        expect(img.attributes('src')).toBe('https://example.com/cover.jpg');
    });

    it('should show placeholder when no cover image', () => {
        const wrapper = createWrapper({ cover_url: null });
        const img = wrapper.find('img');
        expect(img.exists()).toBe(false);
        // Should have placeholder icon
        const placeholderSvg = wrapper.find('.aspect-square svg');
        expect(placeholderSvg.exists()).toBe(true);
    });

    it('should have play button', () => {
        const wrapper = createWrapper({ name: 'Test Playlist' });
        const playButton = wrapper.find('[aria-label="Play Test Playlist"]');
        expect(playButton.exists()).toBe(true);
    });

    it('should play songs when play button is clicked', async () => {
        const songs = [createMockSong({ id: '1' }), createMockSong({ id: '2' })];
        const wrapper = createWrapper({ name: 'Test Playlist', songs });

        const store = usePlayerStore();
        const playSpy = vi.spyOn(store, 'play');

        const playButton = wrapper.find('button');
        await playButton.trigger('click');

        expect(playSpy).toHaveBeenCalledWith(songs, 0);
    });

    it('should not play when no songs available', async () => {
        const wrapper = createWrapper({ songs: [] });

        const store = usePlayerStore();
        const playSpy = vi.spyOn(store, 'play');

        const playButton = wrapper.find('button');
        await playButton.trigger('click');

        expect(playSpy).not.toHaveBeenCalled();
    });

    it('should link to playlist detail page', () => {
        const wrapper = createWrapper({ id: 123 });
        const link = wrapper.find('a');
        const to = JSON.parse(link.attributes('data-to')!);

        expect(to.name).toBe('playlist-detail');
        expect(to.params.slug).toBe(123);
    });

    it('should have proper alt text for cover image', () => {
        const wrapper = createWrapper({
            name: 'My Playlist',
            cover_url: 'https://example.com/cover.jpg',
        });
        const img = wrapper.find('img');
        expect(img.attributes('alt')).toBe('My Playlist');
    });

    it('should calculate song count from songs array when songs_count not available', () => {
        const wrapper = createWrapper({
            description: null,
            songs_count: undefined,
            songs: [createMockSong(), createMockSong(), createMockSong()],
        });
        expect(wrapper.text()).toContain('3 songs');
    });

    it('should show 0 songs when neither songs_count nor songs available', () => {
        const wrapper = createWrapper({
            description: null,
            songs_count: undefined,
            songs: undefined,
        });
        expect(wrapper.text()).toContain('0 songs');
    });
});

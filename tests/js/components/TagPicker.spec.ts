import { describe, it, expect, beforeEach, vi } from 'vitest';
import { mount, flushPromises } from '@vue/test-utils';
import { createPinia, setActivePinia } from 'pinia';
import TagPicker from '@/components/song/TagPicker.vue';
import { createMockTag } from '../utils/test-helpers';
import * as tagsApi from '@/api/tags';

vi.mock('@/api/tags', () => ({
    getTags: vi.fn(),
    createTag: vi.fn(),
}));

describe('TagPicker', () => {
    beforeEach(() => {
        setActivePinia(createPinia());
        vi.clearAllMocks();
    });

    const mockTags = [
        createMockTag({ id: 1, name: 'Rock', color: '#e74c3c' }),
        createMockTag({ id: 2, name: 'Jazz', color: '#3498db' }),
        createMockTag({ id: 3, name: 'Pop', color: '#9b59b6' }),
    ];

    const createWrapper = (props = {}) => {
        vi.mocked(tagsApi.getTags).mockResolvedValue(mockTags);

        return mount(TagPicker, {
            props: {
                selectedTagIds: [],
                ...props,
            },
            global: {
                stubs: {
                    Teleport: true,
                },
            },
        });
    };

    it('should render the tag picker', async () => {
        const wrapper = createWrapper();
        await flushPromises();

        expect(wrapper.exists()).toBe(true);
        expect(wrapper.text()).toContain('Tags');
    });

    it('should fetch tags on mount', async () => {
        createWrapper();
        await flushPromises();

        expect(tagsApi.getTags).toHaveBeenCalledWith(true);
    });

    it('should display placeholder when no tags selected', async () => {
        const wrapper = createWrapper();
        await flushPromises();

        expect(wrapper.text()).toContain('Click to add tags...');
    });

    it('should display selected tags', async () => {
        const wrapper = createWrapper({ selectedTagIds: [1, 2] });
        await flushPromises();

        expect(wrapper.text()).toContain('Rock');
        expect(wrapper.text()).toContain('Jazz');
        expect(wrapper.text()).not.toContain('Pop');
    });

    it('should show dropdown when clicked', async () => {
        const wrapper = createWrapper();
        await flushPromises();

        const tagDisplay = wrapper.find('.min-h-\\[42px\\]');
        await tagDisplay.trigger('click');

        // Check for search input placeholder
        const searchInput = wrapper.find('input[type="text"]');
        expect(searchInput.exists()).toBe(true);
        expect(searchInput.attributes('placeholder')).toBe('Search or create tag...');
    });

    it('should show available tags in dropdown', async () => {
        const wrapper = createWrapper();
        await flushPromises();

        const tagDisplay = wrapper.find('.min-h-\\[42px\\]');
        await tagDisplay.trigger('click');

        expect(wrapper.text()).toContain('Rock');
        expect(wrapper.text()).toContain('Jazz');
        expect(wrapper.text()).toContain('Pop');
    });

    it('should emit update when tag is toggled', async () => {
        const wrapper = createWrapper({ selectedTagIds: [] });
        await flushPromises();

        // Open dropdown
        const tagDisplay = wrapper.find('.min-h-\\[42px\\]');
        await tagDisplay.trigger('click');

        // Click on Rock tag
        const tagButtons = wrapper.findAll('.max-h-48 button');
        const rockButton = tagButtons.find((b) => b.text().includes('Rock'));
        await rockButton!.trigger('click');

        expect(wrapper.emitted('update:selectedTagIds')).toBeTruthy();
        expect(wrapper.emitted('update:selectedTagIds')![0]).toEqual([[1]]);
    });

    it('should emit update when tag is removed', async () => {
        const wrapper = createWrapper({ selectedTagIds: [1, 2] });
        await flushPromises();

        // Find remove button for Rock tag
        const removeButtons = wrapper.findAll('[aria-label="Remove tag"]');
        expect(removeButtons.length).toBeGreaterThan(0);
        await removeButtons[0].trigger('click');

        expect(wrapper.emitted('update:selectedTagIds')).toBeTruthy();
        expect(wrapper.emitted('update:selectedTagIds')![0]).toEqual([[2]]);
    });

    it('should filter tags based on search query', async () => {
        const wrapper = createWrapper();
        await flushPromises();

        // Open dropdown
        const tagDisplay = wrapper.find('.min-h-\\[42px\\]');
        await tagDisplay.trigger('click');

        // Type in search
        const searchInput = wrapper.find('input[type="text"]');
        await searchInput.setValue('Rock');

        const tagButtons = wrapper.findAll('.max-h-48 button');
        expect(tagButtons.length).toBe(1);
        expect(tagButtons[0].text()).toContain('Rock');
    });

    it('should show create option when search query matches no tags', async () => {
        const wrapper = createWrapper();
        await flushPromises();

        // Open dropdown
        const tagDisplay = wrapper.find('.min-h-\\[42px\\]');
        await tagDisplay.trigger('click');

        // Type new tag name
        const searchInput = wrapper.find('input[type="text"]');
        await searchInput.setValue('NewTag');

        expect(wrapper.text()).toContain('Create "NewTag"');
    });

    it('should create new tag and select it', async () => {
        const newTag = createMockTag({ id: 4, name: 'NewTag', color: '#2ecc71' });
        vi.mocked(tagsApi.createTag).mockResolvedValue(newTag);

        const wrapper = createWrapper({ selectedTagIds: [] });
        await flushPromises();

        // Open dropdown
        const tagDisplay = wrapper.find('.min-h-\\[42px\\]');
        await tagDisplay.trigger('click');

        // Type new tag name
        const searchInput = wrapper.find('input[type="text"]');
        await searchInput.setValue('NewTag');

        // Find and click create button (contains "Create" text)
        const allButtons = wrapper.findAll('button');
        const createButton = allButtons.find((b) => b.text().includes('Create'));
        expect(createButton).toBeDefined();
        await createButton!.trigger('click');
        await flushPromises();

        expect(tagsApi.createTag).toHaveBeenCalledWith({ name: 'NewTag' });
        expect(wrapper.emitted('update:selectedTagIds')).toBeTruthy();
        expect(wrapper.emitted('update:selectedTagIds')![0]).toEqual([[4]]);
    });

    it('should show checkmark for selected tags in dropdown', async () => {
        const wrapper = createWrapper({ selectedTagIds: [1] });
        await flushPromises();

        // Open dropdown
        const tagDisplay = wrapper.find('.min-h-\\[42px\\]');
        await tagDisplay.trigger('click');

        // Rock should have checkmark (selected)
        const rockButton = wrapper.findAll('.max-h-48 button').find((b) => b.text().includes('Rock'));
        expect(rockButton!.find('svg').exists()).toBe(true);
    });

    it('should apply tag color styling to selected tags', async () => {
        const wrapper = createWrapper({ selectedTagIds: [1] });
        await flushPromises();

        const selectedTag = wrapper.find('.inline-flex.items-center');
        const style = selectedTag.attributes('style');
        // Browser converts hex to rgb
        expect(style).toContain('231, 76, 60');
    });

    it('should show loading state while fetching tags', async () => {
        let resolvePromise: (tags: typeof mockTags) => void;
        vi.mocked(tagsApi.getTags).mockReturnValue(
            new Promise((resolve) => {
                resolvePromise = resolve;
            })
        );

        const wrapper = mount(TagPicker, {
            props: { selectedTagIds: [] },
            global: { stubs: { Teleport: true } },
        });

        // Open dropdown while loading
        const tagDisplay = wrapper.find('.min-h-\\[42px\\]');
        await tagDisplay.trigger('click');

        expect(wrapper.text()).toContain('Loading tags...');

        // Resolve and verify loading is gone
        resolvePromise!(mockTags);
        await flushPromises();
        expect(wrapper.text()).not.toContain('Loading tags...');
    });

    it('should show error state when fetching tags fails', async () => {
        vi.mocked(tagsApi.getTags).mockRejectedValue(new Error('Network error'));

        const wrapper = mount(TagPicker, {
            props: { selectedTagIds: [] },
            global: { stubs: { Teleport: true } },
        });
        await flushPromises();

        // Open dropdown
        const tagDisplay = wrapper.find('.min-h-\\[42px\\]');
        await tagDisplay.trigger('click');

        expect(wrapper.text()).toContain('Network error');
    });

    it('should toggle tag off when already selected', async () => {
        const wrapper = createWrapper({ selectedTagIds: [1] });
        await flushPromises();

        // Open dropdown
        const tagDisplay = wrapper.find('.min-h-\\[42px\\]');
        await tagDisplay.trigger('click');

        // Click on already selected Rock tag
        const tagButtons = wrapper.findAll('.max-h-48 button');
        const rockButton = tagButtons.find((b) => b.text().includes('Rock'));
        await rockButton!.trigger('click');

        expect(wrapper.emitted('update:selectedTagIds')).toBeTruthy();
        expect(wrapper.emitted('update:selectedTagIds')![0]).toEqual([[]]);
    });

    it('should not show create option for existing tag name', async () => {
        const wrapper = createWrapper();
        await flushPromises();

        // Open dropdown
        const tagDisplay = wrapper.find('.min-h-\\[42px\\]');
        await tagDisplay.trigger('click');

        // Type existing tag name
        const searchInput = wrapper.find('input[type="text"]');
        await searchInput.setValue('Rock');

        expect(wrapper.text()).not.toContain('Create "Rock"');
    });
});

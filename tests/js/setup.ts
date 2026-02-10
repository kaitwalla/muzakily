import { config } from '@vue/test-utils';
import { createPinia, setActivePinia } from 'pinia';

// Create a fresh Pinia instance before each test
beforeEach(() => {
    setActivePinia(createPinia());
});

// Global stubs for router-link and router-view
config.global.stubs = {
    RouterLink: {
        template: '<a><slot /></a>',
    },
    RouterView: {
        template: '<div><slot /></div>',
    },
    Teleport: {
        template: '<div><slot /></div>',
    },
};

// Mock matchMedia
Object.defineProperty(window, 'matchMedia', {
    writable: true,
    value: vi.fn().mockImplementation((query) => ({
        matches: false,
        media: query,
        onchange: null,
        addListener: vi.fn(),
        removeListener: vi.fn(),
        addEventListener: vi.fn(),
        removeEventListener: vi.fn(),
        dispatchEvent: vi.fn(),
    })),
});

// Mock MediaSession API
Object.defineProperty(navigator, 'mediaSession', {
    writable: true,
    value: {
        metadata: null,
        playbackState: 'none',
        setActionHandler: vi.fn(),
        setPositionState: vi.fn(),
    },
});

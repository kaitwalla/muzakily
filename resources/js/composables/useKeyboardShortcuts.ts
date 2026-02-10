import { onMounted, onUnmounted, ref, type Ref } from 'vue';
import { usePlayerStore } from '@/stores/player';

export interface KeyboardShortcut {
    key: string;
    description: string;
    modifiers?: {
        ctrl?: boolean;
        alt?: boolean;
        shift?: boolean;
        meta?: boolean;
    };
    action: () => void;
}

export interface UseKeyboardShortcutsOptions {
    enabled?: boolean;
    preventDefaultOnMatch?: boolean;
}

export interface UseKeyboardShortcutsReturn {
    isEnabled: Ref<boolean>;
    enable: () => void;
    disable: () => void;
    shortcuts: KeyboardShortcut[];
}

/**
 * Composable for global keyboard shortcuts for the music player.
 */
export function useKeyboardShortcuts(
    options: UseKeyboardShortcutsOptions = {}
): UseKeyboardShortcutsReturn {
    const { enabled = true, preventDefaultOnMatch = true } = options;

    const playerStore = usePlayerStore();
    const isEnabled = ref(enabled);

    const shortcuts: KeyboardShortcut[] = [
        {
            key: ' ',
            description: 'Play/Pause',
            action: () => playerStore.togglePlayPause(),
        },
        {
            key: 'ArrowRight',
            description: 'Next track',
            action: () => playerStore.next(),
        },
        {
            key: 'ArrowLeft',
            description: 'Previous track',
            action: () => playerStore.previous(),
        },
        {
            key: 'ArrowUp',
            description: 'Volume up',
            action: () => {
                const newVolume = Math.min(1, playerStore.volume + 0.05);
                playerStore.setVolume(newVolume);
            },
        },
        {
            key: 'ArrowDown',
            description: 'Volume down',
            action: () => {
                const newVolume = Math.max(0, playerStore.volume - 0.05);
                playerStore.setVolume(newVolume);
            },
        },
        {
            key: 'm',
            description: 'Toggle mute',
            action: () => playerStore.toggleMute(),
        },
        {
            key: 's',
            description: 'Toggle shuffle',
            action: () => playerStore.toggleShuffle(),
        },
        {
            key: 'r',
            description: 'Cycle repeat mode',
            action: () => playerStore.cycleRepeatMode(),
        },
        {
            key: 'ArrowLeft',
            description: 'Seek backward 10s',
            modifiers: { shift: true },
            action: () => {
                const newTime = Math.max(0, playerStore.currentTime - 10);
                playerStore.seek(newTime);
            },
        },
        {
            key: 'ArrowRight',
            description: 'Seek forward 10s',
            modifiers: { shift: true },
            action: () => {
                const newTime = Math.min(playerStore.duration, playerStore.currentTime + 10);
                playerStore.seek(newTime);
            },
        },
        {
            key: '0',
            description: 'Seek to start',
            action: () => playerStore.seek(0),
        },
        {
            key: '1',
            description: 'Seek to 10%',
            action: () => playerStore.seek(playerStore.duration * 0.1),
        },
        {
            key: '2',
            description: 'Seek to 20%',
            action: () => playerStore.seek(playerStore.duration * 0.2),
        },
        {
            key: '3',
            description: 'Seek to 30%',
            action: () => playerStore.seek(playerStore.duration * 0.3),
        },
        {
            key: '4',
            description: 'Seek to 40%',
            action: () => playerStore.seek(playerStore.duration * 0.4),
        },
        {
            key: '5',
            description: 'Seek to 50%',
            action: () => playerStore.seek(playerStore.duration * 0.5),
        },
        {
            key: '6',
            description: 'Seek to 60%',
            action: () => playerStore.seek(playerStore.duration * 0.6),
        },
        {
            key: '7',
            description: 'Seek to 70%',
            action: () => playerStore.seek(playerStore.duration * 0.7),
        },
        {
            key: '8',
            description: 'Seek to 80%',
            action: () => playerStore.seek(playerStore.duration * 0.8),
        },
        {
            key: '9',
            description: 'Seek to 90%',
            action: () => playerStore.seek(playerStore.duration * 0.9),
        },
    ];

    function matchesModifiers(event: KeyboardEvent, modifiers?: KeyboardShortcut['modifiers']): boolean {
        if (!modifiers) {
            return !event.ctrlKey && !event.altKey && !event.shiftKey && !event.metaKey;
        }

        return (
            event.ctrlKey === (modifiers.ctrl ?? false) &&
            event.altKey === (modifiers.alt ?? false) &&
            event.shiftKey === (modifiers.shift ?? false) &&
            event.metaKey === (modifiers.meta ?? false)
        );
    }

    function handleKeyDown(event: KeyboardEvent): void {
        if (!isEnabled.value) return;

        // Don't trigger shortcuts when typing in input fields
        const target = event.target as HTMLElement;
        if (
            target.tagName === 'INPUT' ||
            target.tagName === 'TEXTAREA' ||
            target.isContentEditable
        ) {
            return;
        }

        for (const shortcut of shortcuts) {
            if (event.key === shortcut.key && matchesModifiers(event, shortcut.modifiers)) {
                if (preventDefaultOnMatch) {
                    event.preventDefault();
                }
                shortcut.action();
                return;
            }
        }
    }

    function enable(): void {
        isEnabled.value = true;
    }

    function disable(): void {
        isEnabled.value = false;
    }

    onMounted(() => {
        window.addEventListener('keydown', handleKeyDown);
    });

    onUnmounted(() => {
        window.removeEventListener('keydown', handleKeyDown);
    });

    return {
        isEnabled,
        enable,
        disable,
        shortcuts,
    };
}

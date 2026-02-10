import { onMounted, onUnmounted, ref } from 'vue';
import { usePlayerStore } from '@/stores/player';
import { useDevicesStore } from '@/stores/devices';
import { useAuthStore } from '@/stores/auth';

interface RemoteCommandEvent {
    command: string;
    payload?: {
        song_id?: string;
        position?: number;
        volume?: number;
    };
}

interface QueueUpdatedEvent {
    queue: string[];
    current_index: number;
    position: number;
}

// Echo channel instance type
type EchoChannel = {
    listen: (event: string, callback: (data: unknown) => void) => EchoChannel;
    stopListening: (event: string) => EchoChannel;
};

// Echo type declaration for when laravel-echo is installed
declare global {
    interface Window {
        Echo?: {
            private: (channel: string) => EchoChannel;
            leave: (channel: string) => void;
        };
    }
}

export function useRemotePlayer() {
    const playerStore = usePlayerStore();
    const devicesStore = useDevicesStore();
    const authStore = useAuthStore();

    const isConnected = ref(false);
    const channelName = ref<string | null>(null);

    function handleRemoteCommand(event: RemoteCommandEvent): void {
        switch (event.command) {
            case 'play':
                playerStore.resume();
                break;
            case 'pause':
                playerStore.pause();
                break;
            case 'stop':
                playerStore.clearQueue();
                break;
            case 'next':
                playerStore.next();
                break;
            case 'prev':
                playerStore.previous();
                break;
            case 'seek':
                if (event.payload?.position !== undefined) {
                    playerStore.seek(event.payload.position);
                }
                break;
            case 'volume':
                if (event.payload?.volume !== undefined) {
                    playerStore.setVolume(event.payload.volume);
                }
                break;
            case 'queue_clear':
                playerStore.clearQueue();
                break;
        }
    }

    function handleQueueUpdated(event: QueueUpdatedEvent): void {
        // This is handled by the player store when it receives queue sync events
        // For now, we'll just log it - full implementation would require
        // fetching the songs and updating the queue
        console.log('Queue updated from remote:', event);
    }

    function setupEchoListeners(): void {
        if (!window.Echo || !authStore.user) return;

        const userId = authStore.user.id;
        channelName.value = `user.${userId}`;

        try {
            const channel = window.Echo.private(channelName.value);

            channel
                .listen('.remote.command', (event: unknown) => {
                    handleRemoteCommand(event as RemoteCommandEvent);
                })
                .listen('.queue.updated', (event: unknown) => {
                    handleQueueUpdated(event as QueueUpdatedEvent);
                });

            isConnected.value = true;
        } catch (e) {
            console.warn('Failed to set up Echo listeners:', e);
            isConnected.value = false;
        }
    }

    function cleanupEchoListeners(): void {
        if (!window.Echo || !channelName.value) return;

        try {
            window.Echo.leave(channelName.value);
        } catch (e) {
            console.warn('Failed to leave Echo channel:', e);
        }

        isConnected.value = false;
        channelName.value = null;
    }

    async function initialize(): Promise<void> {
        // Wait for auth to be initialized
        if (!authStore.initialized) {
            await authStore.initialize();
        }

        if (!authStore.isAuthenticated) return;

        // Register this device
        try {
            await devicesStore.registerThisDevice();
        } catch (e) {
            console.warn('Failed to register device:', e);
        }

        // Set up Echo listeners
        setupEchoListeners();
    }

    onMounted(() => {
        initialize();
    });

    onUnmounted(() => {
        cleanupEchoListeners();
    });

    return {
        isConnected,
        initialize,
        cleanup: cleanupEchoListeners,
    };
}

import { onMounted, onUnmounted, ref } from 'vue';
import { useAuthStore } from '@/stores/auth';
import type { EchoPresenceMember } from '@/composables/useRemotePlayer';

export function useCompanionStatus() {
    const authStore = useAuthStore();

    const isConnected = ref(false);
    const gamdlAvailable = ref(false);

    let channelName: string | null = null;

    function updateFromMembers(members: EchoPresenceMember[]): void {
        const companion = members.find(m => m.type === 'companion');
        isConnected.value = companion !== undefined;
        gamdlAvailable.value = (companion?.gamdl_available as boolean | undefined) ?? false;
    }

    function setup(): void {
        if (!window.Echo || !authStore.user) return;

        channelName = `companion.${authStore.user.uuid}`;

        try {
            window.Echo.join(channelName)
                .here((members) => {
                    updateFromMembers(members);
                })
                .joining((member) => {
                    if (member.type === 'companion') {
                        isConnected.value = true;
                        gamdlAvailable.value = (member.gamdl_available as boolean | undefined) ?? false;
                    }
                })
                .leaving((member) => {
                    if (member.type === 'companion') {
                        isConnected.value = false;
                        gamdlAvailable.value = false;
                    }
                });
        } catch (e) {
            console.warn('Failed to join companion presence channel:', e);
        }
    }

    function teardown(): void {
        if (!window.Echo || !channelName) return;
        try {
            window.Echo.leave(channelName);
        } catch (e) {
            console.warn('Failed to leave companion channel:', e);
        }
        isConnected.value = false;
        gamdlAvailable.value = false;
        channelName = null;
    }

    onMounted(() => {
        setup();
    });

    onUnmounted(() => {
        teardown();
    });

    return { isConnected, gamdlAvailable };
}

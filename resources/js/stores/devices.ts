import { defineStore } from 'pinia';
import { ref, computed } from 'vue';
import * as devicesApi from '@/api/devices';
import type { PlayerDevice, RemoteCommand, ControlPayload } from '@/api/devices';

function generateDeviceId(): string {
    // Try to get a stored device ID, or generate a new one
    const storedId = localStorage.getItem('device_id');
    if (storedId) return storedId;

    const newId = crypto.randomUUID();
    localStorage.setItem('device_id', newId);
    return newId;
}

function getDeviceName(): string {
    const userAgent = navigator.userAgent;
    // Check Edge first (modern Edge uses 'Edg', legacy uses 'Edge')
    if (userAgent.includes('Edg') || userAgent.includes('Edge')) return 'Edge Browser';
    if (userAgent.includes('Chrome')) return 'Chrome Browser';
    if (userAgent.includes('Firefox')) return 'Firefox Browser';
    if (userAgent.includes('Safari')) return 'Safari Browser';
    return 'Web Browser';
}

export const useDevicesStore = defineStore('devices', () => {
    const devices = ref<PlayerDevice[]>([]);
    const thisDeviceId = ref<string>(generateDeviceId());
    const activeDeviceId = ref<string | null>(null);
    const loading = ref(false);
    const error = ref<string | null>(null);
    const isRegistered = ref(false);

    const thisDevice = computed(() =>
        devices.value.find(d => d.device_id === thisDeviceId.value)
    );

    const activeDevice = computed(() =>
        devices.value.find(d => d.device_id === activeDeviceId.value)
    );

    const otherDevices = computed(() =>
        devices.value.filter(d => d.device_id !== thisDeviceId.value)
    );

    const isControllingRemote = computed(() =>
        activeDeviceId.value !== null && activeDeviceId.value !== thisDeviceId.value
    );

    async function fetchDevices(): Promise<void> {
        loading.value = true;
        error.value = null;
        try {
            devices.value = await devicesApi.getDevices();

            // Set this device as active if no other device is selected
            if (!activeDeviceId.value && thisDevice.value) {
                activeDeviceId.value = thisDeviceId.value;
            }
        } catch (e) {
            error.value = 'Failed to load devices';
            throw e;
        } finally {
            loading.value = false;
        }
    }

    async function registerThisDevice(): Promise<void> {
        if (isRegistered.value) return;

        loading.value = true;
        error.value = null;
        try {
            await devicesApi.registerDevice({
                device_id: thisDeviceId.value,
                name: getDeviceName(),
                type: 'web',
            });
            isRegistered.value = true;
            activeDeviceId.value = thisDeviceId.value;

            // Fetch all devices after registering
            await fetchDevices();
        } catch (e) {
            error.value = 'Failed to register device';
            throw e;
        } finally {
            loading.value = false;
        }
    }

    async function removeDevice(deviceId: string): Promise<void> {
        loading.value = true;
        error.value = null;
        try {
            await devicesApi.removeDevice(deviceId);
            devices.value = devices.value.filter(d => d.device_id !== deviceId);

            // If we removed this device, reset registration state
            if (deviceId === thisDeviceId.value) {
                isRegistered.value = false;
            }

            // If we removed the active device, switch to another available device
            if (activeDeviceId.value === deviceId) {
                const remainingDevice = devices.value.find(d => d.device_id === thisDeviceId.value)
                    ?? devices.value[0];
                activeDeviceId.value = remainingDevice?.device_id ?? null;
            }
        } catch (e) {
            error.value = 'Failed to remove device';
            throw e;
        } finally {
            loading.value = false;
        }
    }

    function selectDevice(deviceId: string): void {
        activeDeviceId.value = deviceId;
    }

    async function sendRemoteCommand(command: RemoteCommand, payload?: ControlPayload): Promise<void> {
        if (!activeDeviceId.value) return;

        try {
            await devicesApi.sendCommand(activeDeviceId.value, command, payload);
        } catch (e) {
            error.value = 'Failed to send command';
            throw e;
        }
    }

    async function syncQueue(queue: string[], currentIndex?: number, position?: number): Promise<void> {
        try {
            await devicesApi.syncQueue(queue, currentIndex, position);
        } catch (e) {
            error.value = 'Failed to sync queue';
            throw e;
        }
    }

    function isOnline(device: PlayerDevice): boolean {
        const lastSeen = new Date(device.last_seen);
        const now = new Date();
        const diffSeconds = (now.getTime() - lastSeen.getTime()) / 1000;
        return diffSeconds < 60;
    }

    function clearError(): void {
        error.value = null;
    }

    return {
        devices,
        thisDeviceId,
        activeDeviceId,
        loading,
        error,
        isRegistered,
        thisDevice,
        activeDevice,
        otherDevices,
        isControllingRemote,
        fetchDevices,
        registerThisDevice,
        removeDevice,
        selectDevice,
        sendRemoteCommand,
        syncQueue,
        isOnline,
        clearError,
    };
});

<script setup lang="ts">
import { computed, onMounted } from 'vue';
import { useDevicesStore } from '@/stores/devices';
import type { PlayerDevice } from '@/api/devices';

export interface Device {
    id: string;
    name: string;
    type: 'browser' | 'speaker' | 'tv' | 'mobile';
    isActive: boolean;
    isOnline: boolean;
}

const emit = defineEmits<{
    close: [];
    'select-device': [device: Device];
}>();

const devicesStore = useDevicesStore();

const loading = computed(() => devicesStore.loading);
const error = computed(() => devicesStore.error);

const devices = computed<Device[]>(() => {
    return devicesStore.devices.map((device: PlayerDevice) => ({
        id: device.device_id,
        name: device.device_id === devicesStore.thisDeviceId ? 'This Browser' : device.name,
        type: mapDeviceType(device.type),
        isActive: device.device_id === devicesStore.activeDeviceId,
        isOnline: devicesStore.isOnline(device),
    }));
});

function mapDeviceType(type: string): 'browser' | 'speaker' | 'tv' | 'mobile' {
    switch (type) {
        case 'web':
            return 'browser';
        case 'mobile':
            return 'mobile';
        case 'desktop':
            return 'tv';
        default:
            return 'browser';
    }
}

function getDeviceIcon(type: Device['type']): string {
    switch (type) {
        case 'browser':
            return 'M21 3H3c-1.1 0-2 .9-2 2v14c0 1.1.9 2 2 2h18c1.1 0 2-.9 2-2V5c0-1.1-.9-2-2-2zm0 16H3V5h18v14zM9 8h2v8H9zm4 0h2v8h-2z';
        case 'speaker':
            return 'M17 2H7c-1.1 0-2 .9-2 2v16c0 1.1.9 2 2 2h10c1.1 0 2-.9 2-2V4c0-1.1-.9-2-2-2zm-5 2c1.1 0 2 .9 2 2s-.9 2-2 2-2-.9-2-2 .9-2 2-2zm0 16c-2.76 0-5-2.24-5-5s2.24-5 5-5 5 2.24 5 5-2.24 5-5 5zm0-8c-1.66 0-3 1.34-3 3s1.34 3 3 3 3-1.34 3-3-1.34-3-3-3z';
        case 'tv':
            return 'M21 3H3c-1.1 0-2 .9-2 2v12c0 1.1.9 2 2 2h5v2h8v-2h5c1.1 0 1.99-.9 1.99-2L23 5c0-1.1-.9-2-2-2zm0 14H3V5h18v12z';
        case 'mobile':
            return 'M15.5 1h-8C6.12 1 5 2.12 5 3.5v17C5 21.88 6.12 23 7.5 23h8c1.38 0 2.5-1.12 2.5-2.5v-17C18 2.12 16.88 1 15.5 1zm-4 21c-.83 0-1.5-.67-1.5-1.5s.67-1.5 1.5-1.5 1.5.67 1.5 1.5-.67 1.5-1.5 1.5zm4.5-4H7V4h9v14z';
        default:
            return 'M12 3v10.55c-.59-.34-1.27-.55-2-.55-2.21 0-4 1.79-4 4s1.79 4 4 4 4-1.79 4-4V7h4V3h-6z';
    }
}

function selectDevice(device: Device): void {
    devicesStore.selectDevice(device.id);
    emit('select-device', device);
}

async function refreshDevices(): Promise<void> {
    devicesStore.clearError();
    try {
        await devicesStore.fetchDevices();
    } catch {
        // Error is handled by the store
    }
}

onMounted(() => {
    refreshDevices();
});
</script>

<template>
    <div class="bg-gray-800 rounded-lg shadow-xl overflow-hidden w-80">
        <!-- Header -->
        <div class="flex items-center justify-between px-4 py-3 border-b border-gray-700">
            <h3 class="text-lg font-semibold text-white">Connect to a device</h3>
            <button
                @click="emit('close')"
                class="p-1 text-gray-400 hover:text-white transition-colors"
                aria-label="Close"
            >
                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" />
                </svg>
            </button>
        </div>

        <!-- Loading state -->
        <div v-if="loading" class="flex items-center justify-center py-8">
            <svg class="w-6 h-6 text-gray-400 animate-spin" fill="none" viewBox="0 0 24 24" aria-hidden="true">
                <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4" />
                <path
                    class="opacity-75"
                    fill="currentColor"
                    d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"
                />
            </svg>
        </div>

        <!-- Error state -->
        <div v-else-if="error" class="px-4 py-8 text-center">
            <p class="text-red-400">{{ error }}</p>
            <button
                @click="refreshDevices"
                class="mt-2 text-sm text-gray-400 hover:text-white transition-colors"
            >
                Try again
            </button>
        </div>

        <!-- Device list -->
        <div v-else class="py-2">
            <div v-if="devices.length === 0" class="px-4 py-8 text-center">
                <p class="text-gray-400">No devices found</p>
                <p class="text-sm text-gray-500 mt-1">Start playing on another device to see it here</p>
            </div>
            <button
                v-for="device in devices"
                :key="device.id"
                @click="selectDevice(device)"
                :disabled="!device.isOnline"
                :class="[
                    'w-full flex items-center gap-3 px-4 py-3 hover:bg-gray-700/50 transition-colors',
                    device.isActive ? 'text-green-500' : device.isOnline ? 'text-white' : 'text-gray-500',
                    !device.isOnline && 'opacity-50 cursor-not-allowed',
                ]"
            >
                <svg class="w-6 h-6" fill="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                    <path :d="getDeviceIcon(device.type)" />
                </svg>
                <div class="flex-1 text-left">
                    <p class="font-medium">{{ device.name }}</p>
                    <p v-if="device.isActive" class="text-xs text-green-500">Currently playing</p>
                    <p v-else-if="!device.isOnline" class="text-xs text-gray-500">Offline</p>
                </div>
                <svg
                    v-if="device.isActive"
                    class="w-5 h-5"
                    fill="currentColor"
                    viewBox="0 0 24 24"
                    aria-hidden="true"
                >
                    <path d="M9 16.17L4.83 12l-1.42 1.41L9 19 21 7l-1.41-1.41z" />
                </svg>
                <!-- Online indicator -->
                <span
                    v-if="device.isOnline && !device.isActive"
                    class="w-2 h-2 bg-green-500 rounded-full"
                    aria-label="Online"
                />
            </button>
        </div>

        <!-- Refresh button -->
        <div class="px-4 py-3 border-t border-gray-700">
            <button
                @click="refreshDevices"
                :disabled="loading"
                class="w-full flex items-center justify-center gap-2 py-2 text-sm text-gray-400 hover:text-white transition-colors disabled:opacity-50"
            >
                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                    <path
                        stroke-linecap="round"
                        stroke-linejoin="round"
                        stroke-width="2"
                        d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15"
                    />
                </svg>
                Refresh
            </button>
        </div>
    </div>
</template>

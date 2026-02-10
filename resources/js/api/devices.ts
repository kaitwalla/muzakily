import { apiClient } from './client';
import type { Song } from '@/types/models';

export interface PlayerDevice {
    device_id: string;
    name: string;
    type: 'web' | 'mobile' | 'desktop';
    is_playing: boolean;
    current_song: Song | null;
    position: number | null;
    volume: number | null;
    last_seen: string;
}

export interface DeviceRegistration {
    device_id: string;
    name: string;
    type: 'web' | 'mobile' | 'desktop';
}

export interface RegisteredDevice {
    device_id: string;
    name: string;
    type: string;
    is_playing: boolean;
    created_at: string;
}

export type RemoteCommand = 'play' | 'pause' | 'stop' | 'next' | 'prev' | 'seek' | 'volume' | 'queue_add' | 'queue_clear';

export interface ControlPayload {
    song_id?: string;
    position?: number;
    volume?: number;
    queue?: string[];
}

export interface PlayerState {
    active_device: { device_id: string; name: string } | null;
    is_playing: boolean;
    current_song: Song | null;
    position: number;
    volume: number;
    queue: Song[];
}

export async function getDevices(): Promise<PlayerDevice[]> {
    const response = await apiClient.get<{ data: PlayerDevice[] }>('/player/devices');
    return response.data.data;
}

export async function registerDevice(data: DeviceRegistration): Promise<RegisteredDevice> {
    const response = await apiClient.post<{ data: RegisteredDevice }>('/player/devices', data);
    return response.data.data;
}

export async function removeDevice(deviceId: string): Promise<void> {
    await apiClient.delete(`/player/devices/${deviceId}`);
}

export async function sendCommand(
    targetDeviceId: string,
    command: RemoteCommand,
    payload?: ControlPayload
): Promise<void> {
    await apiClient.post('/player/control', {
        target_device_id: targetDeviceId,
        command,
        payload,
    });
}

export async function getPlayerState(): Promise<PlayerState> {
    const response = await apiClient.get<{ data: PlayerState }>('/player/state');
    return response.data.data;
}

export async function syncQueue(
    queue: string[],
    currentIndex?: number,
    position?: number
): Promise<{ devices_notified: number }> {
    const response = await apiClient.post<{ data: { status: string; devices_notified: number } }>('/player/sync', {
        queue,
        current_index: currentIndex,
        position,
    });
    return { devices_notified: response.data.data.devices_notified };
}

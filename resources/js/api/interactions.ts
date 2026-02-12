import { apiClient } from './client';

export async function recordPlay(songId: string): Promise<void> {
    await apiClient.post('/interactions/play', { song_id: songId });
}

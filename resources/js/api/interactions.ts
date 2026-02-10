import { apiClient } from './client';

export async function recordPlay(songId: number): Promise<void> {
    await apiClient.post('/interactions/play', { song_id: songId });
}

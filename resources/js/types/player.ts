import type { Song } from './models';

export interface PlaybackState {
    isPlaying: boolean;
    currentTime: number;
    duration: number;
    volume: number;
    isMuted: boolean;
    isShuffled: boolean;
    repeatMode: RepeatMode;
}

export type RepeatMode = 'off' | 'all' | 'one';

export interface QueueItem {
    id: string;
    song: Song;
}

export interface Queue {
    items: QueueItem[];
    currentIndex: number;
}

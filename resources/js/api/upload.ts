import axios from 'axios';
import { getAuthToken } from './client';

export interface UploadResponse {
    job_id: string;
    status: 'processing';
    filename: string;
}

export interface UploadError {
    message: string;
    code?: string;
}

/**
 * Upload a song file with progress tracking.
 */
export async function uploadSong(
    file: File,
    onProgress?: (percent: number) => void
): Promise<UploadResponse> {
    const formData = new FormData();
    formData.append('file', file);

    const token = getAuthToken();
    if (!token) {
        throw new Error('Authentication required');
    }

    const response = await axios.post<{ data: UploadResponse }>(
        '/api/v1/upload',
        formData,
        {
            headers: {
                'Content-Type': 'multipart/form-data',
                'Authorization': `Bearer ${token}`,
                'Accept': 'application/json',
            },
            onUploadProgress: (progressEvent) => {
                if (onProgress && progressEvent.total) {
                    const percent = Math.round(
                        (progressEvent.loaded * 100) / progressEvent.total
                    );
                    onProgress(percent);
                }
            },
        }
    );

    return response.data.data;
}

/**
 * Validate file type for upload.
 */
export function isValidAudioFile(file: File): boolean {
    const validTypes = [
        'audio/mpeg',       // mp3
        'audio/mp4',        // m4a
        'audio/x-m4a',      // m4a (alternative)
        'audio/flac',       // flac
        'audio/x-flac',     // flac (alternative)
    ];

    const validExtensions = ['mp3', 'm4a', 'flac'];
    const extension = file.name.split('.').pop()?.toLowerCase();

    return validTypes.includes(file.type) ||
           (extension !== undefined && validExtensions.includes(extension));
}

/**
 * Get maximum file size in bytes (100MB).
 */
export function getMaxFileSize(): number {
    return 100 * 1024 * 1024; // 100MB
}

/**
 * Format file size for display.
 */
export function formatFileSize(bytes: number): string {
    if (bytes < 1024) {
        return `${bytes} B`;
    } else if (bytes < 1024 * 1024) {
        return `${(bytes / 1024).toFixed(1)} KB`;
    } else {
        return `${(bytes / (1024 * 1024)).toFixed(1)} MB`;
    }
}

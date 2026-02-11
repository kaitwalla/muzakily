import type { SmartPlaylistField, SmartPlaylistFieldValue, FieldType } from './types';

/**
 * Smart playlist field definitions matching the backend SmartPlaylistField enum.
 */
export const smartPlaylistFields: SmartPlaylistField[] = [
    { value: 'title', label: 'Title', type: 'text' },
    { value: 'artist_name', label: 'Artist', type: 'text' },
    { value: 'album_name', label: 'Album', type: 'text' },
    { value: 'genre', label: 'Genre', type: 'text' },
    { value: 'year', label: 'Year', type: 'number' },
    { value: 'length', label: 'Length', type: 'number' },
    { value: 'play_count', label: 'Play Count', type: 'number' },
    { value: 'last_played', label: 'Last Played', type: 'date' },
    { value: 'date_added', label: 'Date Added', type: 'date' },
    { value: 'audio_format', label: 'Format', type: 'text' },
];

/**
 * Get a field definition by its value.
 */
export function getFieldByValue(value: SmartPlaylistFieldValue): SmartPlaylistField | undefined {
    return smartPlaylistFields.find((f) => f.value === value);
}

/**
 * Get the field type for a given field value.
 */
export function getFieldType(value: SmartPlaylistFieldValue): FieldType {
    return getFieldByValue(value)?.type ?? 'text';
}


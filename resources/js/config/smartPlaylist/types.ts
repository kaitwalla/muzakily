/**
 * Smart Playlist Types
 * These types mirror the backend PHP enums and structures for smart playlist functionality.
 */

export type FieldType = 'text' | 'number' | 'date';

export type SmartPlaylistFieldValue =
    | 'title'
    | 'artist_name'
    | 'album_name'
    | 'genre'
    | 'year'
    | 'length'
    | 'play_count'
    | 'last_played'
    | 'date_added'
    | 'smart_folder'
    | 'audio_format';

export type SmartPlaylistOperatorValue =
    // Text operators
    | 'is'
    | 'is_not'
    | 'contains'
    | 'not_contains'
    | 'begins_with'
    | 'ends_with'
    // Number operators
    | 'is_greater_than'
    | 'is_less_than'
    | 'is_between'
    // Date operators
    | 'in_last'
    | 'not_in_last';

export type RuleLogic = 'and' | 'or';

export interface SmartPlaylistField {
    value: SmartPlaylistFieldValue;
    label: string;
    type: FieldType;
}

export interface SmartPlaylistOperator {
    value: SmartPlaylistOperatorValue;
    label: string;
    requiresRange: boolean;
    isDateRange: boolean;
}

export type SmartPlaylistRuleValue = string | number | [string | number, string | number];

export interface SmartPlaylistRule {
    id: string;
    field: SmartPlaylistFieldValue;
    operator: SmartPlaylistOperatorValue;
    value: SmartPlaylistRuleValue;
}

export interface SmartPlaylistRuleGroup {
    id: number;
    logic: RuleLogic;
    rules: SmartPlaylistRule[];
}

export interface SmartPlaylistRules {
    groups: SmartPlaylistRuleGroup[];
}

export interface SmartFolder {
    id: string;
    name: string;
    path: string;
}

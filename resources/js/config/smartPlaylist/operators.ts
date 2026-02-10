import type { SmartPlaylistOperator, SmartPlaylistOperatorValue, FieldType } from './types';

/**
 * Smart playlist operator definitions matching the backend SmartPlaylistOperator enum.
 */
export const smartPlaylistOperators: SmartPlaylistOperator[] = [
    // Text operators
    { value: 'is', label: 'is', requiresRange: false, isDateRange: false },
    { value: 'is_not', label: 'is not', requiresRange: false, isDateRange: false },
    { value: 'contains', label: 'contains', requiresRange: false, isDateRange: false },
    { value: 'not_contains', label: 'does not contain', requiresRange: false, isDateRange: false },
    { value: 'begins_with', label: 'begins with', requiresRange: false, isDateRange: false },
    { value: 'ends_with', label: 'ends with', requiresRange: false, isDateRange: false },

    // Number operators
    { value: 'is_greater_than', label: 'is greater than', requiresRange: false, isDateRange: false },
    { value: 'is_less_than', label: 'is less than', requiresRange: false, isDateRange: false },
    { value: 'is_between', label: 'is between', requiresRange: true, isDateRange: false },

    // Date operators
    { value: 'in_last', label: 'in the last', requiresRange: false, isDateRange: true },
    { value: 'not_in_last', label: 'not in the last', requiresRange: false, isDateRange: true },
];

/**
 * Get an operator definition by its value.
 */
export function getOperatorByValue(value: SmartPlaylistOperatorValue): SmartPlaylistOperator | undefined {
    return smartPlaylistOperators.find((o) => o.value === value);
}

/**
 * Get allowed operators for a given field type.
 */
export function getOperatorsForFieldType(type: FieldType): SmartPlaylistOperator[] {
    switch (type) {
        case 'text':
            return smartPlaylistOperators.filter((o) =>
                ['is', 'is_not', 'contains', 'not_contains', 'begins_with', 'ends_with'].includes(o.value)
            );
        case 'number':
            return smartPlaylistOperators.filter((o) =>
                ['is', 'is_not', 'is_greater_than', 'is_less_than', 'is_between'].includes(o.value)
            );
        case 'date':
            return smartPlaylistOperators.filter((o) =>
                ['in_last', 'not_in_last', 'is_between'].includes(o.value)
            );
        default:
            return [];
    }
}

/**
 * Check if an operator requires a range input (two values).
 */
export function operatorRequiresRange(value: SmartPlaylistOperatorValue): boolean {
    return getOperatorByValue(value)?.requiresRange ?? false;
}

/**
 * Check if an operator is for date fields with day count.
 */
export function operatorIsDateRange(value: SmartPlaylistOperatorValue): boolean {
    return getOperatorByValue(value)?.isDateRange ?? false;
}

<?php

declare(strict_types=1);

namespace App\Enums;

enum SmartPlaylistOperator: string
{
    // Text operators
    case IS = 'is';
    case IS_NOT = 'is_not';
    case CONTAINS = 'contains';
    case NOT_CONTAINS = 'not_contains';
    case BEGINS_WITH = 'begins_with';
    case ENDS_WITH = 'ends_with';

    // Number operators
    case IS_GREATER_THAN = 'is_greater_than';
    case IS_LESS_THAN = 'is_less_than';
    case IS_BETWEEN = 'is_between';

    // Date operators
    case IN_LAST = 'in_last';
    case NOT_IN_LAST = 'not_in_last';

    /**
     * Get the display name for this operator.
     */
    public function displayName(): string
    {
        return match ($this) {
            self::IS => 'is',
            self::IS_NOT => 'is not',
            self::CONTAINS => 'contains',
            self::NOT_CONTAINS => 'does not contain',
            self::BEGINS_WITH => 'begins with',
            self::ENDS_WITH => 'ends with',
            self::IS_GREATER_THAN => 'is greater than',
            self::IS_LESS_THAN => 'is less than',
            self::IS_BETWEEN => 'is between',
            self::IN_LAST => 'in the last',
            self::NOT_IN_LAST => 'not in the last',
        };
    }

    /**
     * Check if this operator requires two values (for range operations).
     */
    public function requiresRange(): bool
    {
        return $this === self::IS_BETWEEN;
    }

    /**
     * Check if this operator is for date fields with day count.
     */
    public function isDateRange(): bool
    {
        return in_array($this, [self::IN_LAST, self::NOT_IN_LAST], true);
    }
}

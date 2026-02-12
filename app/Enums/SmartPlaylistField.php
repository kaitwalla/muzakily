<?php

declare(strict_types=1);

namespace App\Enums;

enum SmartPlaylistField: string
{
    case TITLE = 'title';
    case ARTIST_NAME = 'artist_name';
    case ALBUM_NAME = 'album_name';
    case GENRE = 'genre';
    case YEAR = 'year';
    case LENGTH = 'length';
    case PLAY_COUNT = 'play_count';
    case LAST_PLAYED = 'last_played';
    case DATE_ADDED = 'date_added';
    case AUDIO_FORMAT = 'audio_format';
    case IS_FAVORITE = 'is_favorite';
    case TAG = 'tag';

    /**
     * Get the data type for this field.
     */
    public function type(): string
    {
        return match ($this) {
            self::TITLE,
            self::ARTIST_NAME,
            self::ALBUM_NAME,
            self::GENRE,
            self::AUDIO_FORMAT => 'text',

            self::YEAR,
            self::LENGTH,
            self::PLAY_COUNT => 'number',

            self::LAST_PLAYED,
            self::DATE_ADDED => 'date',

            self::IS_FAVORITE => 'boolean',

            self::TAG => 'text',
        };
    }

    /**
     * Get allowed operators for this field.
     *
     * @return array<SmartPlaylistOperator>
     */
    public function allowedOperators(): array
    {
        return match ($this->type()) {
            'text' => [
                SmartPlaylistOperator::IS,
                SmartPlaylistOperator::IS_NOT,
                SmartPlaylistOperator::CONTAINS,
                SmartPlaylistOperator::NOT_CONTAINS,
                SmartPlaylistOperator::BEGINS_WITH,
                SmartPlaylistOperator::ENDS_WITH,
            ],
            'number' => [
                SmartPlaylistOperator::IS,
                SmartPlaylistOperator::IS_NOT,
                SmartPlaylistOperator::IS_GREATER_THAN,
                SmartPlaylistOperator::IS_LESS_THAN,
                SmartPlaylistOperator::IS_BETWEEN,
            ],
            'date' => [
                SmartPlaylistOperator::IN_LAST,
                SmartPlaylistOperator::NOT_IN_LAST,
                SmartPlaylistOperator::IS_BETWEEN,
            ],
            'boolean' => [
                SmartPlaylistOperator::IS,
                SmartPlaylistOperator::IS_NOT,
            ],
            default => [],
        };
    }

    /**
     * Get the database column name for this field.
     */
    public function column(): string
    {
        return match ($this) {
            self::TITLE => 'title',
            self::ARTIST_NAME => 'artist_name',
            self::ALBUM_NAME => 'album_name',
            self::GENRE => 'genre',
            self::YEAR => 'year',
            self::LENGTH => 'length',
            self::PLAY_COUNT => 'play_count',
            self::LAST_PLAYED => 'last_played_at',
            self::DATE_ADDED => 'created_at',
            self::AUDIO_FORMAT => 'audio_format',
            self::IS_FAVORITE => 'is_favorite', // Virtual column, handled specially
            self::TAG => 'tag', // Virtual column, handled specially via relationship
        };
    }

    /**
     * Get the display name for this field.
     */
    public function displayName(): string
    {
        return match ($this) {
            self::TITLE => 'Title',
            self::ARTIST_NAME => 'Artist',
            self::ALBUM_NAME => 'Album',
            self::GENRE => 'Genre',
            self::YEAR => 'Year',
            self::LENGTH => 'Length',
            self::PLAY_COUNT => 'Play Count',
            self::LAST_PLAYED => 'Last Played',
            self::DATE_ADDED => 'Date Added',
            self::AUDIO_FORMAT => 'Format',
            self::IS_FAVORITE => 'Favorite',
            self::TAG => 'Tag',
        };
    }
}

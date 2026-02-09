<?php

return [
    /*
    |--------------------------------------------------------------------------
    | Smart Folders Configuration
    |--------------------------------------------------------------------------
    |
    | Configure how smart folders are extracted from storage paths.
    |
    */
    'smart_folders' => [
        // Folders that use second-level categorization (e.g., Xmas/Contemporary)
        'special' => explode(',', env('MUZAKILY_SPECIAL_FOLDERS', 'Xmas,Holiday,Seasonal')),

        // Default depth for normal folders
        'default_depth' => 1,
    ],

    /*
    |--------------------------------------------------------------------------
    | R2 Storage Configuration
    |--------------------------------------------------------------------------
    |
    | Cloudflare R2 storage settings for the music library.
    |
    */
    'r2' => [
        'bucket' => env('R2_BUCKET'),
        'endpoint' => env('R2_ENDPOINT'),
        'url' => env('R2_URL'),

        // Presigned URL expiry time in seconds
        'presigned_expiry' => env('R2_PRESIGNED_EXPIRY', 3600),
    ],

    /*
    |--------------------------------------------------------------------------
    | Transcoding Configuration
    |--------------------------------------------------------------------------
    |
    | Configure audio transcoding settings.
    |
    */
    'transcoding' => [
        // Default bitrate for transcoded files
        'default_bitrate' => env('MUZAKILY_TRANSCODE_BITRATE', 256),

        // Available bitrates
        'bitrates' => [128, 192, 256, 320],

        // Available formats
        'formats' => ['mp3', 'aac'],

        // Storage path prefix for transcoded files
        'storage_prefix' => 'transcodes',
    ],

    /*
    |--------------------------------------------------------------------------
    | Streaming Configuration
    |--------------------------------------------------------------------------
    |
    | Configure streaming behavior.
    |
    */
    'streaming' => [
        // Default streaming format (original, mp3, aac)
        'default_format' => env('MUZAKILY_STREAM_FORMAT', 'original'),
    ],

    /*
    |--------------------------------------------------------------------------
    | Library Scanning Configuration
    |--------------------------------------------------------------------------
    |
    | Configure how the library scanner operates.
    |
    */
    'scanning' => [
        // Supported audio file extensions
        'extensions' => ['mp3', 'aac', 'm4a', 'flac'],

        // Maximum concurrent jobs during scanning
        'max_concurrent_jobs' => env('MUZAKILY_SCAN_CONCURRENCY', 5),

        // Batch size for processing files
        'batch_size' => env('MUZAKILY_SCAN_BATCH_SIZE', 100),
    ],

    /*
    |--------------------------------------------------------------------------
    | Metadata Configuration
    |--------------------------------------------------------------------------
    |
    | Configure metadata extraction and enrichment.
    |
    */
    'metadata' => [
        // MusicBrainz settings
        'musicbrainz' => [
            'enabled' => env('MUSICBRAINZ_ENABLED', true),
            'rate_limit' => env('MUSICBRAINZ_RATE_LIMIT', 1), // requests per second
        ],

        // Last.fm settings (for artist images and bios)
        'lastfm' => [
            'enabled' => env('LASTFM_ENABLED', false),
            'api_key' => env('LASTFM_API_KEY'),
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Player Configuration
    |--------------------------------------------------------------------------
    |
    | Configure remote player control settings.
    |
    */
    'player' => [
        // How long until a device is considered offline (seconds)
        'device_timeout' => env('MUZAKILY_DEVICE_TIMEOUT', 60),

        // How often to clean up stale devices (hours)
        'cleanup_threshold' => env('MUZAKILY_DEVICE_CLEANUP', 24),
    ],
];

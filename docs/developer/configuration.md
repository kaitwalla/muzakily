# Configuration

Environment variables and configuration options for Muzakily.

## Environment Variables

### Application

| Variable | Description | Default |
|----------|-------------|---------|
| `APP_NAME` | Application name | Muzakily |
| `APP_ENV` | Environment (local, staging, production) | production |
| `APP_DEBUG` | Enable debug mode | false |
| `APP_URL` | Base URL | http://localhost |
| `APP_KEY` | Encryption key (generate with artisan) | - |

### Database

| Variable | Description | Default |
|----------|-------------|---------|
| `DB_CONNECTION` | Database driver | pgsql |
| `DB_HOST` | Database host | 127.0.0.1 |
| `DB_PORT` | Database port | 5432 |
| `DB_DATABASE` | Database name | muzakily |
| `DB_USERNAME` | Database user | - |
| `DB_PASSWORD` | Database password | - |

### Redis

| Variable | Description | Default |
|----------|-------------|---------|
| `REDIS_HOST` | Redis host | 127.0.0.1 |
| `REDIS_PORT` | Redis port | 6379 |
| `REDIS_PASSWORD` | Redis password | null |

### Cloudflare R2 Storage

| Variable | Description | Default |
|----------|-------------|---------|
| `R2_ACCESS_KEY_ID` | R2 access key | - |
| `R2_SECRET_ACCESS_KEY` | R2 secret key | - |
| `R2_BUCKET` | R2 bucket name | - |
| `R2_ENDPOINT` | R2 endpoint URL | - |
| `R2_URL` | Public URL for bucket | - |
| `R2_REGION` | R2 region | auto |

### Meilisearch

| Variable | Description | Default |
|----------|-------------|---------|
| `MEILISEARCH_HOST` | Meilisearch URL | http://localhost:7700 |
| `MEILISEARCH_KEY` | Meilisearch master key | - |

### Pusher (Real-time)

| Variable | Description | Default |
|----------|-------------|---------|
| `PUSHER_APP_ID` | Pusher app ID | - |
| `PUSHER_APP_KEY` | Pusher key | - |
| `PUSHER_APP_SECRET` | Pusher secret | - |
| `PUSHER_APP_CLUSTER` | Pusher cluster | mt1 |

### Queue

| Variable | Description | Default |
|----------|-------------|---------|
| `QUEUE_CONNECTION` | Queue driver | redis |

### Session

| Variable | Description | Default |
|----------|-------------|---------|
| `SESSION_DRIVER` | Session driver | redis |
| `SESSION_LIFETIME` | Session lifetime (minutes) | 120 |

### Mail

| Variable | Description | Default |
|----------|-------------|---------|
| `MAIL_MAILER` | Mail driver | smtp |
| `MAIL_HOST` | SMTP host | - |
| `MAIL_PORT` | SMTP port | 587 |
| `MAIL_USERNAME` | SMTP username | - |
| `MAIL_PASSWORD` | SMTP password | - |
| `MAIL_FROM_ADDRESS` | From email | - |
| `MAIL_FROM_NAME` | From name | ${APP_NAME} |

## Application Configuration

### config/muzakily.php

```php
return [
    // Maximum upload file size in kilobytes
    'max_upload_size' => env('MAX_UPLOAD_SIZE', 102400),

    // Supported audio formats
    'audio_formats' => ['mp3', 'm4a', 'flac'],

    // Transcoding settings
    'transcoding' => [
        'enabled' => env('TRANSCODING_ENABLED', true),
        'default_format' => env('TRANSCODING_FORMAT', 'mp3'),
        'default_bitrate' => env('TRANSCODING_BITRATE', 256),
    ],

    // Streaming URL expiration (seconds)
    'stream_url_ttl' => env('STREAM_URL_TTL', 3600),

    // Library scanning
    'scanning' => [
        'batch_size' => env('SCAN_BATCH_SIZE', 100),
        'concurrent_jobs' => env('SCAN_CONCURRENT_JOBS', 4),
    ],

    // Search settings
    'search' => [
        'min_query_length' => 2,
        'max_results' => 50,
    ],
];
```

### Filesystem Configuration

In `config/filesystems.php`:

```php
'r2' => [
    'driver' => 's3',
    'key' => env('R2_ACCESS_KEY_ID'),
    'secret' => env('R2_SECRET_ACCESS_KEY'),
    'region' => env('R2_REGION', 'auto'),
    'bucket' => env('R2_BUCKET'),
    'url' => env('R2_URL'),
    'endpoint' => env('R2_ENDPOINT'),
    'use_path_style_endpoint' => true,
    'throw' => true,
],
```

## Cache Configuration

### config/cache.php

```php
'default' => env('CACHE_DRIVER', 'redis'),

'stores' => [
    'redis' => [
        'driver' => 'redis',
        'connection' => 'cache',
        'lock_connection' => 'default',
    ],
],

// Cache prefixes by type
'prefix' => [
    'songs' => 'songs:',
    'albums' => 'albums:',
    'artists' => 'artists:',
    'playlists' => 'playlists:',
    'search' => 'search:',
],
```

## Queue Configuration

### config/queue.php

```php
'connections' => [
    'redis' => [
        'driver' => 'redis',
        'connection' => 'default',
        'queue' => env('REDIS_QUEUE', 'default'),
        'retry_after' => 90,
        'block_for' => null,
    ],
],

// Named queues for different job types
'queues' => [
    'scanning' => 'scanning',      // Library scan jobs
    'processing' => 'processing',  // File processing
    'enrichment' => 'enrichment',  // Metadata enrichment
    'default' => 'default',        // Everything else
],
```

## Rate Limiting

### config/muzakily.php

```php
'rate_limits' => [
    'api' => [
        'requests' => 60,
        'per_minutes' => 1,
    ],
    'upload' => [
        'requests' => 10,
        'per_minutes' => 1,
    ],
    'search' => [
        'requests' => 30,
        'per_minutes' => 1,
    ],
],
```

## Environment-Specific Settings

### Local Development

```env
APP_ENV=local
APP_DEBUG=true
LOG_LEVEL=debug
```

### Staging

```env
APP_ENV=staging
APP_DEBUG=true
LOG_LEVEL=info
```

### Production

```env
APP_ENV=production
APP_DEBUG=false
LOG_LEVEL=warning
```

## Security Configuration

### CORS (config/cors.php)

```php
'paths' => ['api/*'],
'allowed_methods' => ['*'],
'allowed_origins' => [env('APP_URL')],
'allowed_headers' => ['*'],
'supports_credentials' => true,
```

### Sanctum (config/sanctum.php)

```php
'stateful' => explode(',', env('SANCTUM_STATEFUL_DOMAINS', '')),
'expiration' => null, // Tokens don't expire
```

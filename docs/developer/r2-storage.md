# Cloudflare R2 Storage

Muzakily uses Cloudflare R2 for storing audio files. R2 provides S3-compatible object storage with no egress fees.

## Setup

### Create R2 Bucket

1. Log into Cloudflare Dashboard
2. Go to R2 → Create bucket
3. Name your bucket (e.g., `muzakily`)
4. Choose your region

### Create API Token

1. Go to R2 → Manage R2 API Tokens
2. Create a new token with:
   - Object Read & Write
   - Permissions for your bucket
3. Save the Access Key ID and Secret Access Key

### Configure Environment

```env
R2_ACCESS_KEY_ID=your_access_key_id
R2_SECRET_ACCESS_KEY=your_secret_access_key
R2_BUCKET=muzakily
R2_ENDPOINT=https://your-account-id.r2.cloudflarestorage.com
R2_URL=https://your-public-domain.com
R2_REGION=auto
```

## Bucket Structure

```
muzakily/
├── music/              # Audio files
│   ├── Rock/
│   │   └── artist-album-track.flac
│   └── Pop/
│       └── artist-album-track.mp3
├── covers/             # Album artwork
│   └── album-uuid.jpg
├── artists/            # Artist images
│   └── artist-uuid.jpg
└── transcoded/         # Transcoded files
    └── song-uuid-format-bitrate.mp3
```

## Laravel Configuration

### Filesystem Disk

In `config/filesystems.php`:

```php
'disks' => [
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
        'visibility' => 'private',
    ],
],
```

### Default Disk

```php
'default' => env('FILESYSTEM_DISK', 'r2'),
```

## Usage in Code

### Storing Files

```php
use Illuminate\Support\Facades\Storage;

// Store uploaded file
Storage::disk('r2')->putFileAs(
    'music/Rock',
    $uploadedFile,
    'song-filename.flac'
);

// Store from string
Storage::disk('r2')->put('covers/album.jpg', $imageContents);
```

### Retrieving Files

```php
// Get file contents
$contents = Storage::disk('r2')->get('music/song.flac');

// Check existence
if (Storage::disk('r2')->exists('music/song.flac')) {
    // File exists
}

// Get file size
$size = Storage::disk('r2')->size('music/song.flac');
```

### Generating Presigned URLs

```php
// Streaming URL (1 hour expiry)
$url = Storage::disk('r2')->temporaryUrl(
    'music/song.flac',
    now()->addHour()
);

// Download URL with content-disposition
$url = Storage::disk('r2')->temporaryUrl(
    'music/song.flac',
    now()->addHour(),
    [
        'ResponseContentDisposition' => 'attachment; filename="song.flac"',
    ]
);
```

### Deleting Files

```php
// Delete single file
Storage::disk('r2')->delete('music/song.flac');

// Delete multiple files
Storage::disk('r2')->delete(['file1.mp3', 'file2.mp3']);
```

### Listing Files

```php
// List files in directory
$files = Storage::disk('r2')->files('music/Rock');

// List all files recursively
$allFiles = Storage::disk('r2')->allFiles('music');

// List directories
$directories = Storage::disk('r2')->directories('music');
```

## Streaming Service

The `StreamingService` handles URL generation:

```php
namespace App\Services;

class StreamingService
{
    public function getStreamingUrl(Song $song, ?string $format = null, int $bitrate = 256): array
    {
        if ($format && $format !== 'original') {
            $path = $this->getTranscodedPath($song, $format, $bitrate);
        } else {
            $path = $song->file_path;
        }

        return [
            'url' => Storage::disk('r2')->temporaryUrl(
                $path,
                now()->addHour()
            ),
            'audio_format' => $format ?? $song->audio_format,
            'audio_length' => $song->length,
        ];
    }
}
```

## Transcoding Storage

Transcoded files are cached in R2:

```php
$transcodedPath = sprintf(
    'transcoded/%s-%s-%d.%s',
    $song->id,
    $format,
    $bitrate,
    $format
);
```

## Public Access

For public content (covers, artist images):

### Using R2 Public Bucket

1. Enable public access in R2 settings
2. Configure public URL in `.env`:
   ```env
   R2_URL=https://pub-xxxxx.r2.dev
   ```

### Using Cloudflare CDN

1. Create a custom domain for R2
2. Configure in Cloudflare DNS
3. Update `R2_URL` to use custom domain

## Migration from Local Storage

```php
// Migrate existing files to R2
$localFiles = Storage::disk('local')->allFiles('music');

foreach ($localFiles as $file) {
    $contents = Storage::disk('local')->get($file);
    Storage::disk('r2')->put($file, $contents);
}
```

## Best Practices

### Organize by Path

Use meaningful paths that enable smart folders:
```
music/Genre/Artist - Album/01 - Track.flac
```

### Handle Failures

```php
try {
    $url = Storage::disk('r2')->temporaryUrl($path, now()->addHour());
} catch (UnableToGenerateTemporaryUrl $e) {
    // Handle failure
    Log::error('Failed to generate URL', ['path' => $path]);
    throw new StreamingException('Unable to stream file');
}
```

### Validate Uploads

```php
$request->validate([
    'file' => [
        'required',
        'file',
        'max:102400', // 100MB
        'mimes:mp3,m4a,flac',
    ],
]);
```

## Troubleshooting

### Connection Issues

```php
// Test connection
try {
    Storage::disk('r2')->exists('test');
    echo "Connected!";
} catch (\Exception $e) {
    echo "Error: " . $e->getMessage();
}
```

### CORS Issues

Configure CORS in Cloudflare R2 dashboard:

```json
[
  {
    "AllowedOrigins": ["https://your-domain.com"],
    "AllowedMethods": ["GET", "HEAD"],
    "AllowedHeaders": ["*"],
    "ExposeHeaders": ["Content-Length", "Content-Type"],
    "MaxAgeSeconds": 3600
  }
]
```

### URL Signature Errors

- Verify access key and secret are correct
- Check system time is synchronized
- Ensure bucket name matches exactly

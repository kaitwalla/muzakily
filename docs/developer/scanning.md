# Library Scanning

The library scanner reads audio files from R2 storage, extracts metadata, and creates database records.

## Overview

Scanning involves:

1. Listing files in R2 bucket
2. Filtering for supported audio formats
3. Extracting metadata from each file
4. Creating/updating database records
5. Assigning smart folders
6. Indexing in Meilisearch

## Triggering a Scan

### Via Admin API

```bash
curl -X POST "https://api.example.com/api/v1/admin/library/scan" \
  -H "Authorization: Bearer {admin_token}" \
  -H "Content-Type: application/json" \
  -d '{"force": false}'
```

### Via Artisan

```bash
docker compose exec app php artisan library:scan
docker compose exec app php artisan library:scan --force
```

### Scheduled Scan

In `app/Console/Kernel.php`:

```php
$schedule->command('library:scan')->daily();
```

## Scanner Service

### LibraryScannerService

```php
namespace App\Services;

class LibraryScannerService
{
    public function scan(bool $force = false): ScanResult
    {
        $files = $this->listAudioFiles();
        $results = new ScanResult();

        foreach ($files as $file) {
            try {
                $this->processFile($file, $force, $results);
            } catch (Exception $e) {
                $results->addError($file, $e->getMessage());
            }
        }

        return $results;
    }

    protected function listAudioFiles(): Collection
    {
        return collect(Storage::disk('r2')->allFiles('music'))
            ->filter(fn ($file) => $this->isAudioFile($file));
    }

    protected function processFile(string $path, bool $force, ScanResult $results): void
    {
        $hash = $this->getFileHash($path);
        $existing = Song::where('file_hash', $hash)->first();

        if ($existing && !$force) {
            $results->addSkipped($path);
            return;
        }

        $metadata = $this->extractMetadata($path);
        $song = $this->createOrUpdateSong($path, $hash, $metadata);

        if ($existing) {
            $results->addUpdated($song);
        } else {
            $results->addNew($song);
        }
    }
}
```

## Metadata Extraction

### Supported Tags

| Format | Tag Standard |
|--------|--------------|
| MP3 | ID3v2.4, ID3v2.3, ID3v1 |
| AAC/M4A | iTunes-style atoms |
| FLAC | Vorbis Comments |

### Extracted Fields

```php
[
    'title' => 'Song Title',
    'artist' => 'Artist Name',
    'album' => 'Album Name',
    'album_artist' => 'Album Artist',
    'track' => 1,
    'disc' => 1,
    'year' => 2024,
    'genre' => 'Rock',
    'duration' => 245, // seconds
    'bitrate' => 320,
    'sample_rate' => 44100,
    'channels' => 2,
    'cover' => '...binary data...',
]
```

### Using getID3

```php
use getID3;

class MetadataExtractor
{
    protected getID3 $getID3;

    public function extract(string $path): array
    {
        $tempFile = $this->downloadToTemp($path);

        try {
            $info = $this->getID3->analyze($tempFile);
            return $this->normalizeMetadata($info, $path);
        } finally {
            unlink($tempFile);
        }
    }

    protected function normalizeMetadata(array $info, string $path): array
    {
        return [
            'title' => $info['tags']['id3v2']['title'][0] ?? basename($path),
            'artist' => $info['tags']['id3v2']['artist'][0] ?? 'Unknown Artist',
            'album' => $info['tags']['id3v2']['album'][0] ?? 'Unknown Album',
            'year' => (int) ($info['tags']['id3v2']['year'][0] ?? null),
            'track' => (int) ($info['tags']['id3v2']['track_number'][0] ?? null),
            'genre' => $info['tags']['id3v2']['genre'][0] ?? '',
            'duration' => (int) ($info['playtime_seconds'] ?? 0),
            // ... more fields
        ];
    }
}
```

## Smart Folder Assignment

During scanning, smart folders are created based on file paths:

```php
class SmartFolderService
{
    public function assignFromPath(Song $song, string $path): void
    {
        $parts = explode('/', dirname($path));
        $parent = null;

        foreach ($parts as $depth => $name) {
            $folder = SmartFolder::firstOrCreate(
                ['path' => implode('/', array_slice($parts, 0, $depth + 1))],
                [
                    'name' => $name,
                    'parent_id' => $parent?->id,
                    'depth' => $depth,
                ]
            );
            $parent = $folder;
        }

        $song->smart_folder_id = $parent?->id;
        $song->save();
    }
}
```

## Artist/Album Resolution

### Finding or Creating Artists

```php
class ArtistResolver
{
    public function resolve(string $name): Artist
    {
        // Normalize name
        $normalized = $this->normalize($name);

        return Artist::firstOrCreate(
            ['name' => $normalized],
            [
                'slug' => Str::slug($normalized),
            ]
        );
    }

    protected function normalize(string $name): string
    {
        // Handle "The Beatles" vs "Beatles, The"
        if (preg_match('/^(.+),\s*The$/i', $name, $matches)) {
            return 'The ' . trim($matches[1]);
        }

        return trim($name);
    }
}
```

### Finding or Creating Albums

```php
class AlbumResolver
{
    public function resolve(string $name, Artist $artist, ?int $year = null): Album
    {
        return Album::firstOrCreate(
            [
                'name' => $name,
                'artist_id' => $artist->id,
            ],
            [
                'slug' => Str::slug($name),
                'year' => $year,
            ]
        );
    }
}
```

## Cover Art Extraction

```php
class CoverArtService
{
    public function extractAndStore(string $path, Album $album): ?string
    {
        $cover = $this->extractCover($path);

        if (!$cover) {
            return null;
        }

        $coverPath = sprintf('covers/%s.jpg', $album->id);
        Storage::disk('r2')->put($coverPath, $cover);

        return $coverPath;
    }

    protected function extractCover(string $path): ?string
    {
        $tempFile = $this->downloadToTemp($path);
        $info = $this->getID3->analyze($tempFile);

        return $info['comments']['picture'][0]['data'] ?? null;
    }
}
```

## Background Processing

### Scan Job

```php
class ScanLibraryJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public function __construct(
        public bool $force = false
    ) {}

    public function handle(LibraryScannerService $scanner): void
    {
        $result = $scanner->scan($this->force);

        event(new LibraryScanCompleted($result));
    }
}
```

### Chunked Processing

For large libraries, process in chunks:

```php
class ScanLibraryJob implements ShouldQueue
{
    public function handle(LibraryScannerService $scanner): void
    {
        $files = Storage::disk('r2')->allFiles('music');

        collect($files)
            ->filter(fn ($f) => $this->isAudioFile($f))
            ->chunk(100)
            ->each(function ($chunk) {
                ProcessFilesJob::dispatch($chunk->toArray());
            });
    }
}
```

## Scan Status Tracking

```php
class ScanProgressTracker
{
    public function start(int $totalFiles): void
    {
        Cache::put('library_scan', [
            'status' => 'scanning',
            'total' => $totalFiles,
            'processed' => 0,
            'new' => 0,
            'updated' => 0,
            'errors' => 0,
            'started_at' => now(),
        ], 3600);
    }

    public function increment(string $type): void
    {
        $data = Cache::get('library_scan');
        $data['processed']++;
        $data[$type]++;
        Cache::put('library_scan', $data, 3600);
    }

    public function complete(): void
    {
        $data = Cache::get('library_scan');
        $data['status'] = 'completed';
        $data['completed_at'] = now();
        Cache::put('library_scan', $data, 3600);
    }
}
```

## Incremental Scanning

Only process new/changed files:

```php
class IncrementalScanner
{
    public function scan(): ScanResult
    {
        $lastScan = Cache::get('last_scan_time');

        $files = Storage::disk('r2')->allFiles('music')
            ->filter(fn ($file) =>
                Storage::disk('r2')->lastModified($file) > $lastScan
            );

        // Process only modified files...

        Cache::put('last_scan_time', now()->timestamp);
    }
}
```

## Error Handling

```php
class ScanErrorHandler
{
    public function handle(string $path, Exception $e): void
    {
        Log::error('Scan error', [
            'file' => $path,
            'error' => $e->getMessage(),
        ]);

        ScanError::create([
            'file_path' => $path,
            'error_message' => $e->getMessage(),
            'error_class' => get_class($e),
            'occurred_at' => now(),
        ]);
    }
}
```

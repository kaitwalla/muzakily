<?php

declare(strict_types=1);

namespace App\Services\Library;

use App\Enums\AudioFormat;
use App\Models\Album;
use App\Models\Artist;
use App\Models\ScanCache;
use App\Models\SmartFolder;
use App\Models\Song;
use App\Services\Storage\R2StorageService;
use Closure;

class LibraryScannerService
{
    public function __construct(
        private R2StorageService $r2Storage,
        private MetadataExtractorService $metadataExtractor,
        private SmartFolderService $smartFolderService,
    ) {}

    /**
     * Scan the R2 bucket for music files.
     *
     * @param Closure(array{total_files: int, scanned_files: int, new_songs: int, updated_songs: int, errors: int}): void|null $onProgress
     */
    public function scan(bool $force = false, ?Closure $onProgress = null): void
    {
        $bucket = config('filesystems.disks.r2.bucket');
        $extensions = config('muzakily.scanning.extensions', ['mp3', 'aac', 'm4a', 'flac']);

        $stats = [
            'total_files' => 0,
            'scanned_files' => 0,
            'new_songs' => 0,
            'updated_songs' => 0,
            'errors' => 0,
        ];

        foreach ($this->r2Storage->listObjects() as $object) {
            $stats['total_files']++;

            // Check file extension
            $extension = strtolower(pathinfo($object['key'], PATHINFO_EXTENSION));
            if (!in_array($extension, $extensions, true)) {
                continue;
            }

            try {
                $result = $this->processFile($bucket, $object, $force);

                if ($result === 'new') {
                    $stats['new_songs']++;
                } elseif ($result === 'updated') {
                    $stats['updated_songs']++;
                }

                $stats['scanned_files']++;
            } catch (\Throwable $e) {
                $stats['errors']++;
                report($e);
            }

            if ($onProgress && $stats['scanned_files'] % 10 === 0) {
                $onProgress($stats);
            }
        }

        // Update smart folder song counts
        SmartFolder::all()->each->updateSongCount();

        if ($onProgress) {
            $onProgress($stats);
        }
    }

    /**
     * Process a single file.
     *
     * @param array{key: string, size: int, last_modified: \DateTimeInterface, etag: string} $object
     */
    private function processFile(string $bucket, array $object, bool $force): ?string
    {
        $cache = ScanCache::findOrCreateForKey($bucket, $object['key']);

        // Check if file has changed
        if (!$force && !$cache->hasChanged($object['etag'], $object['size'])) {
            $cache->markScanned();
            return null;
        }

        // Check if song already exists
        $existingSong = Song::findByStoragePath($object['key']);

        // Download file temporarily for metadata extraction
        $tempPath = tempnam(sys_get_temp_dir(), 'muzakily_scan_');

        if ($tempPath === false) {
            throw new \RuntimeException('Failed to create temporary file for metadata extraction');
        }

        try {
            $this->r2Storage->download($object['key'], $tempPath);

            // Extract metadata
            $metadata = $this->metadataExtractor->extract($tempPath);

            // Determine audio format
            $format = AudioFormat::fromExtension(pathinfo($object['key'], PATHINFO_EXTENSION));

            if (!$format) {
                return null;
            }

            // Find or create artist
            $artist = null;
            if ($metadata['artist'] ?? null) {
                $artist = Artist::findOrCreateByName($metadata['artist']);
            }

            // Find or create album
            $album = null;
            if ($metadata['album'] ?? null) {
                $album = Album::findOrCreateByNameAndArtist($metadata['album'], $artist);
                if ($metadata['year'] ?? null) {
                    $album->update(['year' => $metadata['year']]);
                }
            }

            // Assign smart folder
            $smartFolder = $this->smartFolderService->assignFromPath($object['key']);

            // Create or update song
            $songData = [
                'album_id' => $album?->id,
                'artist_id' => $artist?->id,
                'smart_folder_id' => $smartFolder?->id,
                'title' => $metadata['title'] ?? pathinfo($object['key'], PATHINFO_FILENAME),
                'album_name' => $metadata['album'] ?? null,
                'artist_name' => $metadata['artist'] ?? null,
                'length' => $metadata['duration'],
                'track' => $metadata['track'] ?? null,
                'disc' => $metadata['disc'] ?? 1,
                'year' => $metadata['year'] ?? null,
                'storage_path' => $object['key'],
                'file_hash' => $object['etag'],
                'file_size' => $object['size'],
                'mime_type' => $format->mimeType(),
                'audio_format' => $format->value,
                'r2_etag' => $object['etag'],
                'r2_last_modified' => $object['last_modified'],
            ];

            if ($existingSong) {
                $existingSong->update($songData);
                $result = 'updated';
            } else {
                Song::create($songData);
                $result = 'new';
            }

            // Update cache
            $cache->updateFromScan($object['etag'], $object['size'], $object['last_modified']);

            return $result;
        } finally {
            @unlink($tempPath);
        }
    }
}

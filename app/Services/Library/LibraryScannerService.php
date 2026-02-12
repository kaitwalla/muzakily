<?php

declare(strict_types=1);

namespace App\Services\Library;

use App\Contracts\MusicStorageInterface;
use App\Enums\AudioFormat;
use App\Models\Album;
use App\Models\Artist;
use App\Models\ScanCache;
use App\Models\SmartFolder;
use App\Models\Song;
use App\Services\Library\TagService;
use Closure;
use Illuminate\Support\Facades\DB;

class LibraryScannerService
{
    public function __construct(
        private MusicStorageInterface $storage,
        private MetadataExtractorService $metadataExtractor,
        private SmartFolderService $smartFolderService,
        private TagService $tagService,
    ) {}

    /**
     * Scan the storage for music files.
     *
     * @param Closure(array{total_files: int, scanned_files: int, new_songs: int, updated_songs: int, errors: int, removed_songs: int}): void|null $onProgress
     */
    public function scan(bool $force = false, ?int $limit = null, ?Closure $onProgress = null): void
    {
        $storageDriver = config('muzakily.storage.driver', 'r2');
        $bucket = $storageDriver === 'local' ? 'local' : (string) config('filesystems.disks.r2.bucket');
        $extensions = config('muzakily.scanning.extensions', ['mp3', 'aac', 'm4a', 'flac']);

        // Record scan start time for orphan detection
        $scanStartedAt = now();

        $stats = [
            'total_files' => 0,
            'scanned_files' => 0,
            'new_songs' => 0,
            'updated_songs' => 0,
            'errors' => 0,
            'removed_songs' => 0,
        ];

        foreach ($this->storage->listObjects() as $object) {
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

            // Check if limit reached
            if ($limit !== null && $stats['scanned_files'] >= $limit) {
                break;
            }
        }

        // Prune orphaned entries (files that no longer exist in R2)
        $stats['removed_songs'] = $this->pruneOrphans($bucket, $scanStartedAt);

        // Update smart folder song counts
        SmartFolder::all()->each->updateSongCount();

        // Update tag song counts
        \App\Models\Tag::all()->each->updateSongCount();

        if ($onProgress) {
            $onProgress($stats);
        }
    }

    /**
     * Remove database entries for files that no longer exist in R2.
     *
     * Files that weren't seen during the scan (last_scanned_at < scan start time)
     * are considered deleted from R2 and their database entries are removed.
     *
     * @return int Number of songs removed
     */
    public function pruneOrphans(string $bucket, \DateTimeInterface $scanStartedAt): int
    {
        $removedCount = 0;

        // Find stale cache entries (not scanned during this run)
        $staleEntries = ScanCache::stale($bucket, $scanStartedAt)->get();

        foreach ($staleEntries as $entry) {
            DB::transaction(function () use ($entry, &$removedCount) {
                // Delete associated song if exists
                $song = Song::findByStoragePath($entry->object_key);
                if ($song) {
                    // Detach from tags
                    $song->tags()->detach();
                    // Delete the song
                    $song->delete();
                    $removedCount++;
                }

                // Delete the cache entry
                $entry->delete();
            });
        }

        return $removedCount;
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

        // Try partial download first for efficiency
        $metadata = $this->extractMetadataWithPartialDownload($object);

        // Fall back to full download if partial extraction failed or has no duration
        if ($metadata === null || ($metadata['duration'] <= 0)) {
            $metadata = $this->extractMetadataWithFullDownload($object['key']);
        }

        if ($metadata === null) {
            return null;
        }

        // Determine audio format
        $format = AudioFormat::fromExtension(pathinfo($object['key'], PATHINFO_EXTENSION));

        if (!$format) {
            return null;
        }

        // Wrap all database operations in a transaction to prevent partial state
        return DB::transaction(function () use ($object, $metadata, $format, $existingSong, $cache) {
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
                $song = $existingSong;
                $result = 'updated';
            } else {
                $song = Song::create($songData);
                $result = 'new';
            }

            // Assign tag based on folder path
            if (config('muzakily.tags.auto_create_from_folders', true)) {
                $this->tagService->assignFromPath($song);
            }

            // Update cache
            $cache->updateFromScan($object['etag'], $object['size'], $object['last_modified']);

            return $result;
        });
    }

    /**
     * Extract metadata using partial download (header + footer only).
     *
     * This is more efficient for large files as it only downloads the parts
     * of the file that contain metadata, typically ~640KB instead of the full file.
     *
     * @param array{key: string, size: int, last_modified: \DateTimeInterface, etag: string} $object
     * @return array{
     *     title: string|null,
     *     artist: string|null,
     *     album: string|null,
     *     year: int|null,
     *     track: int|null,
     *     disc: int|null,
     *     genre: string|null,
     *     duration: float,
     *     bitrate: int|null,
     *     lyrics: string|null,
     * }|null
     */
    private function extractMetadataWithPartialDownload(array $object): ?array
    {
        $partial = $this->storage->downloadPartial($object['key']);

        if ($partial === null) {
            return null;
        }

        $tempPath = $this->storage->createPartialTempFile(
            $partial['header'],
            $partial['footer'],
            $partial['file_size']
        );

        if ($tempPath === false) {
            return null;
        }

        try {
            // We use the regular extract method because createPartialTempFile makes a valid-looking file
            return $this->metadataExtractor->extract($tempPath);
        } catch (\Throwable $e) {
            // If extraction fails on partial file, return null to trigger fallback
            return null;
        } finally {
            @unlink($tempPath);
        }
    }

    /**
     * Extract metadata using full file download.
     *
     * This is used as a fallback when partial download fails or doesn't
     * provide complete metadata (e.g., missing duration).
     *
     * @return array{
     *     title: string|null,
     *     artist: string|null,
     *     album: string|null,
     *     year: int|null,
     *     track: int|null,
     *     disc: int|null,
     *     genre: string|null,
     *     duration: float,
     *     bitrate: int|null,
     *     lyrics: string|null,
     * }|null
     */
    private function extractMetadataWithFullDownload(string $key): ?array
    {
        $tempPath = tempnam(sys_get_temp_dir(), 'muzakily_scan_');

        if ($tempPath === false) {
            throw new \RuntimeException('Failed to create temporary file for metadata extraction');
        }

        try {
            if (!$this->storage->download($key, $tempPath)) {
                return null;
            }

            return $this->metadataExtractor->extract($tempPath);
        } finally {
            @unlink($tempPath);
        }
    }
}

<?php

declare(strict_types=1);

namespace App\Jobs;

use App\Contracts\MusicStorageInterface;
use App\Enums\AudioFormat;
use App\Jobs\FetchArtistImageJob;
use App\Models\Album;
use App\Models\Artist;
use App\Models\ScanCache;
use App\Models\Song;
use App\Services\Library\CoverArtService;
use App\Services\Library\MetadataExtractorService;
use App\Services\Library\SmartFolderService;
use App\Services\Library\TagService;
use Illuminate\Bus\Batchable;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class ScanFileBatchJob implements ShouldQueue
{
    use Batchable, Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    /**
     * The number of times the job may be attempted.
     */
    public int $tries = 1;

    /**
     * The number of seconds the job can run before timing out.
     */
    public int $timeout = 600; // 10 minutes per batch

    /**
     * Create a new job instance.
     *
     * @param array<array{key: string, size: int, last_modified: string, etag: string}> $files
     */
    public function __construct(
        public array $files,
        public string $bucket,
        public bool $force = false,
    ) {}

    /**
     * Execute the job.
     */
    public function handle(
        MusicStorageInterface $storage,
        SmartFolderService $smartFolderService,
        TagService $tagService,
        CoverArtService $coverArtService,
    ): void {
        if ($this->batch()?->cancelled()) {
            return;
        }

        $extensions = config('muzakily.scanning.extensions', ['mp3', 'aac', 'm4a', 'flac']);

        foreach ($this->files as $file) {
            try {
                // Restore DateTimeInterface from serialized string
                $object = [
                    'key' => $file['key'],
                    'size' => $file['size'],
                    'last_modified' => new \DateTimeImmutable($file['last_modified']),
                    'etag' => $file['etag'],
                ];

                // Check file extension
                $extension = strtolower(pathinfo($object['key'], PATHINFO_EXTENSION));
                if (!in_array($extension, $extensions, true)) {
                    continue;
                }

                $this->processFile(
                    $storage,
                    $smartFolderService,
                    $tagService,
                    $coverArtService,
                    $object,
                );
            } catch (\Throwable $e) {
                Log::error('Failed to process file in batch', [
                    'key' => $file['key'],
                    'error' => $e->getMessage(),
                ]);
                report($e);
            }

            // Aggressive memory cleanup after each file
            gc_collect_cycles();
        }
    }

    /**
     * Process a single file.
     *
     * @param array{key: string, size: int, last_modified: \DateTimeInterface, etag: string} $object
     */
    private function processFile(
        MusicStorageInterface $storage,
        SmartFolderService $smartFolderService,
        TagService $tagService,
        CoverArtService $coverArtService,
        array $object,
    ): void {
        $cache = ScanCache::findOrCreateForKey($this->bucket, $object['key']);

        // Check if file has changed
        if (!$this->force && !$cache->hasChanged($object['etag'], $object['size'])) {
            $cache->markScanned();
            return;
        }

        // Create fresh extractor for each file to avoid memory accumulation
        $metadataExtractor = new MetadataExtractorService();

        // Check if song already exists
        $existingSong = Song::findByStoragePath($object['key']);

        // Extract metadata
        $localPath = $storage->getLocalPath($object['key']);

        if ($localPath !== null && file_exists($localPath)) {
            $metadata = $metadataExtractor->safeExtract($localPath);
        } else {
            $metadata = $this->extractMetadataWithPartialDownload($storage, $metadataExtractor, $object);

            if ($metadata === null || (($metadata['duration'] ?? 0) <= 0)) {
                $metadata = $this->extractMetadataWithFullDownload($storage, $metadataExtractor, $object['key']);
            }
        }

        if ($metadata === null) {
            return;
        }

        // Determine audio format
        $format = AudioFormat::fromExtension(strtolower(pathinfo($object['key'], PATHINFO_EXTENSION)));

        if (!$format) {
            return;
        }

        // Wrap all database operations in a transaction
        DB::transaction(function () use ($object, $metadata, $format, $existingSong, $cache, $smartFolderService, $tagService, $coverArtService) {
            // Find or create artist
            $artist = null;
            if ($metadata['artist'] ?? null) {
                $artist = Artist::findOrCreateByName($metadata['artist']);
                $artistForJob = $artist;
                DB::afterCommit(fn () => FetchArtistImageJob::dispatchIfNeeded($artistForJob));
            }

            // Find or create album
            $album = null;
            if ($metadata['album'] ?? null) {
                $album = Album::findOrCreateByNameAndArtist($metadata['album'], $artist);
                if ($metadata['year'] ?? null) {
                    $album->update(['year' => $metadata['year']]);
                }

                if (!$album->cover && !empty($metadata['cover_art'])) {
                    $coverUrl = $coverArtService->storeForAlbum($album, $metadata['cover_art']);
                    if ($coverUrl) {
                        $album->update(['cover' => $coverUrl]);
                    }
                }
            }

            // Assign smart folder
            $smartFolder = $smartFolderService->assignFromPath($object['key']);

            // Create or update song
            $songData = [
                'album_id' => $album?->id,
                'artist_id' => $artist?->id,
                'smart_folder_id' => $smartFolder?->id,
                'title' => $metadata['title'] ?? pathinfo($object['key'], PATHINFO_FILENAME),
                'album_name' => $metadata['album'] ?? null,
                'artist_name' => $metadata['artist'] ?? null,
                'length' => $metadata['duration'] ?? 0,
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
            } else {
                $song = Song::create($songData);
            }

            // Assign tag based on folder path
            if (config('muzakily.tags.auto_create_from_folders', true)) {
                $tagService->assignFromPath($song);
            }

            // Update cache
            $cache->updateFromScan($object['etag'], $object['size'], $object['last_modified']);
        });
    }

    /**
     * Extract metadata using partial download.
     *
     * @param array{key: string, size: int, last_modified: \DateTimeInterface, etag: string} $object
     * @return array<string, mixed>|null
     */
    private function extractMetadataWithPartialDownload(
        MusicStorageInterface $storage,
        MetadataExtractorService $metadataExtractor,
        array $object,
    ): ?array {
        $partial = $storage->downloadPartial($object['key']);

        if ($partial === null) {
            return null;
        }

        $tempPath = $storage->createPartialTempFile(
            $partial['header'],
            $partial['footer'],
            $partial['file_size']
        );

        if ($tempPath === false) {
            return null;
        }

        try {
            return $metadataExtractor->safeExtract($tempPath);
        } catch (\Throwable $e) {
            return null;
        } finally {
            @unlink($tempPath);
        }
    }

    /**
     * Extract metadata using full file download.
     *
     * @return array<string, mixed>|null
     */
    private function extractMetadataWithFullDownload(
        MusicStorageInterface $storage,
        MetadataExtractorService $metadataExtractor,
        string $key,
    ): ?array {
        $tempPath = tempnam(sys_get_temp_dir(), 'muzakily_scan_');

        if ($tempPath === false) {
            throw new \RuntimeException('Failed to create temporary file');
        }

        try {
            if (!$storage->download($key, $tempPath)) {
                return null;
            }

            return $metadataExtractor->safeExtract($tempPath);
        } finally {
            @unlink($tempPath);
        }
    }
}

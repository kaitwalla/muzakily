<?php

declare(strict_types=1);

namespace App\Jobs;

use App\Contracts\MusicStorageInterface;
use App\Models\ScanCache;
use App\Models\SmartFolder;
use App\Models\Song;
use App\Services\Library\LibraryScannerService;
use App\Services\Metadata\MetadataAggregatorService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class LibraryScanJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    /**
     * The number of times the job may be attempted.
     */
    public int $tries = 1;

    /**
     * The number of seconds the job can run before timing out.
     */
    public int $timeout = 3600; // 1 hour

    /**
     * Cache key for tracking scan status.
     */
    private const STATUS_CACHE_KEY = 'library_scan_status';

    /**
     * Cache TTL for status (2 hours).
     */
    private const STATUS_CACHE_TTL = 7200;

    /**
     * Number of files to process per batch job.
     */
    private const BATCH_SIZE = 100;

    /**
     * Create a new job instance.
     */
    public function __construct(
        public bool $force = false,
        public ?int $limit = null,
        public bool $enrich = false,
    ) {}

    /**
     * Execute the job.
     */
    public function handle(MusicStorageInterface $storage, MetadataAggregatorService $aggregator): void
    {
        $this->updateStatus('scanning', 'Starting library scan...');

        Log::info('Library scan job started', [
            'force' => $this->force,
            'limit' => $this->limit,
            'enrich' => $this->enrich,
        ]);

        $storageDriver = config('muzakily.storage.driver', 'r2');
        $bucket = $storageDriver === 'local'
            ? 'local'
            : (config('filesystems.disks.r2.bucket') ?? throw new \RuntimeException('R2 bucket not configured'));
        $extensions = config('muzakily.scanning.extensions', ['mp3', 'aac', 'm4a', 'flac']);

        // Record scan start time for orphan detection
        $scanStartedAt = now();

        // Collect files and create batch jobs (streaming to avoid memory buildup)
        $this->updateStatus('scanning', 'Collecting files...');

        $batch = [];
        $totalFiles = 0;
        $matchedFiles = 0;
        $jobs = [];

        foreach ($storage->listObjects() as $object) {
            $totalFiles++;

            $extension = strtolower(pathinfo($object['key'], PATHINFO_EXTENSION));
            if (!in_array($extension, $extensions, true)) {
                continue;
            }

            // Serialize DateTimeInterface to string for job serialization
            $batch[] = [
                'key' => $object['key'],
                'size' => $object['size'],
                'last_modified' => $object['last_modified']->format(\DateTimeInterface::ATOM),
                'etag' => $object['etag'],
            ];
            $matchedFiles++;

            // Dispatch batch when full to avoid memory accumulation
            if (count($batch) >= self::BATCH_SIZE) {
                $jobs[] = new ScanFileBatchJob($batch, $bucket, $this->force);
                $batch = [];
            }

            if ($this->limit !== null && $matchedFiles >= $this->limit) {
                break;
            }

            // Update progress periodically
            if ($totalFiles % 1000 === 0) {
                $this->updateStatus('scanning', 'Collecting files...', [
                    'collected' => $matchedFiles,
                    'total_scanned' => $totalFiles,
                ]);
            }
        }

        // Don't forget remaining files
        if (!empty($batch)) {
            $jobs[] = new ScanFileBatchJob($batch, $bucket, $this->force);
        }

        $this->updateStatus('scanning', 'Dispatching batch jobs...', [
            'total_files' => $matchedFiles,
        ]);

        Log::info('Collected files for scanning', [
            'audio_files' => $matchedFiles,
            'total_files' => $totalFiles,
        ]);

        if (empty($jobs)) {
            $this->runPostScanCleanup($bucket, $scanStartedAt, $aggregator);
            return;
        }

        // Store scan context for the completion callback
        $enrich = $this->enrich;
        $scanStartedAtString = $scanStartedAt->toIso8601String();

        Bus::batch($jobs)
            ->name('library-scan')
            ->allowFailures()
            ->finally(function () use ($bucket, $scanStartedAtString, $enrich) {
                // Run post-scan cleanup in a separate job to ensure it runs
                LibraryScanCleanupJob::dispatch($bucket, $scanStartedAtString, $enrich);
            })
            ->onQueue('default')
            ->dispatch();

        $this->updateStatus('scanning', 'Batch jobs dispatched', [
            'batches' => count($jobs),
            'files_per_batch' => self::BATCH_SIZE,
            'total_files' => $matchedFiles,
        ]);

        Log::info('Dispatched scan batches', [
            'batches' => count($jobs),
            'total_files' => $matchedFiles,
        ]);
    }

    /**
     * Run post-scan cleanup (for empty file list case).
     */
    private function runPostScanCleanup(string $bucket, \DateTimeInterface $scanStartedAt, MetadataAggregatorService $aggregator): void
    {
        // Prune orphans
        $removedCount = $this->pruneOrphans($bucket, $scanStartedAt);

        // Update smart folder and tag counts (chunked to avoid memory issues)
        SmartFolder::chunk(100, function ($folders) {
            $folders->each->updateSongCount();
        });
        \App\Models\Tag::chunk(100, function ($tags) {
            $tags->each->updateSongCount();
        });

        $this->updateStatus('scanned', 'Scan complete', [
            'removed_songs' => $removedCount,
        ]);

        if ($this->enrich) {
            $this->enrichMetadata($aggregator);
        }

        $this->updateStatus('completed', 'Library scan completed');
        Log::info('Library scan job completed');
    }

    /**
     * Remove database entries for files that no longer exist in storage.
     */
    private function pruneOrphans(string $bucket, \DateTimeInterface $scanStartedAt): int
    {
        $removedCount = 0;

        ScanCache::stale($bucket, $scanStartedAt)->chunkById(100, function ($staleEntries) use (&$removedCount) {
            foreach ($staleEntries as $entry) {
                DB::transaction(function () use ($entry, &$removedCount) {
                    $song = Song::findByStoragePath($entry->object_key);
                    if ($song) {
                        $song->tags()->detach();
                        $song->forceDelete();
                        $removedCount++;
                    }
                    $entry->delete();
                });
            }
        });

        return $removedCount;
    }

    /**
     * Enrich metadata for songs without MusicBrainz IDs.
     */
    private function enrichMetadata(MetadataAggregatorService $aggregator): void
    {
        $this->updateStatus('enriching', 'Starting metadata enrichment...');

        $total = Song::whereNull('musicbrainz_id')->count();

        if ($total === 0) {
            return;
        }

        $enriched = 0;
        $albumsWithCovers = 0;
        $processed = 0;

        Song::whereNull('musicbrainz_id')
            ->with(['artist', 'album'])
            ->chunkById(100, function ($songs) use ($aggregator, &$enriched, &$albumsWithCovers, &$processed, $total) {
                foreach ($songs as $song) {
                    try {
                        $hadCover = $song->album?->cover !== null;

                        $aggregator->enrich($song);

                        $song->refresh();

                        if ($song->musicbrainz_id) {
                            $enriched++;
                        }

                        if ($song->album && !$hadCover && $song->album->fresh()?->cover !== null) {
                            $albumsWithCovers++;
                        }
                    } catch (\Throwable $e) {
                        report($e);
                    }

                    $processed++;

                    // Update status every 10 songs
                    if ($processed % 10 === 0) {
                        $this->updateStatus('enriching', 'Enriching metadata...', [
                            'enriched' => $enriched,
                            'albums_with_covers' => $albumsWithCovers,
                            'processed' => $processed,
                            'total' => $total,
                        ]);
                    }
                }
            });

        $this->updateStatus('enriched', 'Enrichment complete', [
            'enriched' => $enriched,
            'albums_with_covers' => $albumsWithCovers,
            'total' => $total,
        ]);
    }

    /**
     * Handle a job failure.
     */
    public function failed(?\Throwable $exception): void
    {
        $this->updateStatus('failed', $exception?->getMessage() ?? 'Unknown error');

        Log::error('Library scan job failed', [
            'error' => $exception?->getMessage(),
        ]);
    }

    /**
     * Update the scan status in cache.
     *
     * @param array<string, mixed> $stats
     */
    private function updateStatus(string $status, string $message, array $stats = []): void
    {
        Cache::put(self::STATUS_CACHE_KEY, [
            'status' => $status,
            'message' => $message,
            'stats' => $stats,
            'updated_at' => now()->toIso8601String(),
        ], self::STATUS_CACHE_TTL);
    }

    /**
     * Get the current scan status.
     *
     * @return array{status: string, message: string, stats: array<string, mixed>, updated_at: string}|null
     */
    public static function getStatus(): ?array
    {
        return Cache::get(self::STATUS_CACHE_KEY);
    }

    /**
     * Check if a scan is currently running.
     */
    public static function isRunning(): bool
    {
        $status = self::getStatus();

        if (!$status) {
            return false;
        }

        return in_array($status['status'], ['scanning', 'enriching'], true);
    }

    /**
     * Clear the scan status.
     */
    public static function clearStatus(): void
    {
        Cache::forget(self::STATUS_CACHE_KEY);
    }
}

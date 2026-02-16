<?php

declare(strict_types=1);

namespace App\Jobs;

use App\Models\ScanCache;
use App\Models\Song;
use App\Models\Tag;
use App\Services\Metadata\MetadataAggregatorService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class LibraryScanCleanupJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    /**
     * The number of times the job may be attempted.
     */
    public int $tries = 1;

    /**
     * The number of seconds the job can run before timing out.
     */
    public int $timeout = 1800; // 30 minutes

    /**
     * Cache key for tracking scan status.
     */
    private const STATUS_CACHE_KEY = 'library_scan_status';

    /**
     * Cache TTL for status (2 hours).
     */
    private const STATUS_CACHE_TTL = 7200;

    /**
     * Create a new job instance.
     */
    public function __construct(
        public string $bucket,
        public string $scanStartedAt,
        public bool $enrich = false,
    ) {}

    /**
     * Execute the job.
     */
    public function handle(MetadataAggregatorService $aggregator): void
    {
        $this->updateStatus('cleaning', 'Running post-scan cleanup...');

        Log::info('Library scan cleanup started', [
            'bucket' => $this->bucket,
            'enrich' => $this->enrich,
        ]);

        try {
            $scanStartedAt = new \DateTimeImmutable($this->scanStartedAt);
        } catch (\Exception $e) {
            throw new \InvalidArgumentException("Invalid scanStartedAt format: {$this->scanStartedAt}", 0, $e);
        }

        // Prune orphans
        $removedCount = $this->pruneOrphans($scanStartedAt);

        $this->updateStatus('cleaning', 'Updating tag counts...', [
            'removed_songs' => $removedCount,
        ]);

        // Update tag counts (chunked to avoid memory issues)
        Tag::chunk(100, function ($tags) {
            $tags->each->updateSongCount();
        });

        $this->updateStatus('scanned', 'Scan complete', [
            'removed_songs' => $removedCount,
        ]);

        // Enrich metadata if requested
        if ($this->enrich) {
            $this->enrichMetadata($aggregator);
        }

        $this->updateStatus('completed', 'Library scan completed', [
            'removed_songs' => $removedCount,
        ]);

        Log::info('Library scan cleanup completed', [
            'removed_songs' => $removedCount,
        ]);
    }

    /**
     * Remove database entries for files that no longer exist in storage.
     */
    private function pruneOrphans(\DateTimeInterface $scanStartedAt): int
    {
        $removedCount = 0;

        ScanCache::stale($this->bucket, $scanStartedAt)->chunkById(100, function ($staleEntries) use (&$removedCount) {
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

                    if ($processed % 10 === 0) {
                        $this->updateStatus('enriching', 'Enriching metadata...', [
                            'enriched' => $enriched,
                            'albums_with_covers' => $albumsWithCovers,
                            'processed' => $processed,
                            'total' => $total,
                        ]);
                    }
                }

                // Memory cleanup between chunks
                gc_collect_cycles();
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
        $this->updateStatus('failed', $exception?->getMessage() ?? 'Cleanup failed');

        Log::error('Library scan cleanup failed', [
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
}

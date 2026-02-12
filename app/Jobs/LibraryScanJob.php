<?php

declare(strict_types=1);

namespace App\Jobs;

use App\Models\Song;
use App\Services\Library\LibraryScannerService;
use App\Services\Metadata\MetadataAggregatorService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Cache;
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
    public function handle(LibraryScannerService $scanner, MetadataAggregatorService $aggregator): void
    {
        $this->updateStatus('scanning', 'Starting library scan...');

        Log::info('Library scan job started', [
            'force' => $this->force,
            'limit' => $this->limit,
            'enrich' => $this->enrich,
        ]);

        $scanner->scan(
            force: $this->force,
            limit: $this->limit,
            onProgress: function (array $stats): void {
                $this->updateStatus('scanning', 'Scanning files...', $stats);
            }
        );

        $this->updateStatus('scanned', 'Scan complete');

        // Enrich metadata if requested
        if ($this->enrich) {
            $this->enrichMetadata($aggregator);
        }

        $this->updateStatus('completed', 'Library scan completed');

        Log::info('Library scan job completed');
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

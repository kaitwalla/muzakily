<?php

declare(strict_types=1);

namespace App\Jobs;

use App\Models\Artist;
use App\Services\Metadata\ArtistImageService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Cache;

class FetchArtistImageJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    /**
     * The number of times the job may be attempted.
     */
    public int $tries = 3;

    /**
     * The number of seconds to wait before retrying the job.
     */
    public int $backoff = 60;

    /**
     * Cache key prefix for tracking pending jobs.
     */
    private const PENDING_CACHE_PREFIX = 'artist_image_pending:';

    /**
     * Cache TTL for pending status (1 hour).
     */
    private const PENDING_CACHE_TTL = 3600;

    /**
     * Create a new job instance.
     */
    public function __construct(
        public int $artistId,
    ) {}

    /**
     * Execute the job.
     */
    public function handle(ArtistImageService $imageService): void
    {
        $artist = Artist::find($this->artistId);

        if (!$artist) {
            $this->clearPendingStatus();
            return;
        }

        // Skip if artist already has an image
        if ($artist->image) {
            $this->clearPendingStatus();
            return;
        }

        // Only clear pending status on success
        // If an exception is thrown, the job will retry and we want to keep the pending flag
        $imageService->fetchAndStore($artist);
        $this->clearPendingStatus();
    }

    /**
     * Handle a job failure (called after all retries are exhausted).
     */
    public function failed(?\Throwable $exception): void
    {
        $this->clearPendingStatus();
    }

    /**
     * Clear the pending status from cache.
     */
    private function clearPendingStatus(): void
    {
        Cache::forget(self::PENDING_CACHE_PREFIX . $this->artistId);
    }

    /**
     * Dispatch the job if appropriate, using atomic cache operation.
     * Returns false if artist already has an image or a job is pending.
     */
    public static function dispatchIfNeeded(Artist $artist): bool
    {
        // Already has an image - no need for atomic check
        if ($artist->image) {
            return false;
        }

        // Atomically check and set pending status
        // Cache::add() only sets the value if the key doesn't exist
        $cacheKey = self::PENDING_CACHE_PREFIX . $artist->id;
        if (!Cache::add($cacheKey, true, self::PENDING_CACHE_TTL)) {
            // Another job is already pending
            return false;
        }

        self::dispatch($artist->id);

        return true;
    }
}

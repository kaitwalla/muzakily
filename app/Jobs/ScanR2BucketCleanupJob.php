<?php

declare(strict_types=1);

namespace App\Jobs;

use App\Models\ScanCache;
use App\Models\SmartFolder;
use App\Models\Song;
use App\Models\Tag;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class ScanR2BucketCleanupJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    /**
     * The number of times the job may be attempted.
     */
    public int $tries = 1;

    /**
     * The number of seconds the job can run before timing out.
     */
    public int $timeout = 600;

    /**
     * Create a new job instance.
     */
    public function __construct(
        public string $jobId,
        public string $bucket,
        public string $scanStartedAt,
    ) {}

    /**
     * Execute the job.
     */
    public function handle(): void
    {
        Log::info('ScanR2BucketCleanupJob started', [
            'job_id' => $this->jobId,
            'bucket' => $this->bucket,
        ]);

        try {
            $scanStartedAt = new \DateTimeImmutable($this->scanStartedAt);
        } catch (\Exception $e) {
            throw new \InvalidArgumentException("Invalid scanStartedAt format: {$this->scanStartedAt}", 0, $e);
        }

        // Prune orphans
        $removedCount = $this->pruneOrphans($scanStartedAt);

        // Update smart folder and tag counts (chunked to avoid memory issues)
        SmartFolder::chunk(100, function ($folders) {
            $folders->each->updateSongCount();
        });
        Tag::chunk(100, function ($tags) {
            $tags->each->updateSongCount();
        });

        // Mark as completed
        $current = Cache::get("scan_status:{$this->jobId}", []);
        $current['status'] = 'completed';
        $current['completed_at'] = now()->toIso8601String();
        $current['progress']['removed_songs'] = $removedCount;
        Cache::put("scan_status:{$this->jobId}", $current, 3600);

        // Clear current job marker
        Cache::put('scan_current_job', null, 60);

        Log::info('ScanR2BucketCleanupJob completed', [
            'job_id' => $this->jobId,
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
     * Handle a job failure.
     */
    public function failed(?\Throwable $exception): void
    {
        Log::error('ScanR2BucketCleanupJob failed', [
            'job_id' => $this->jobId,
            'error' => $exception?->getMessage(),
        ]);

        $current = Cache::get("scan_status:{$this->jobId}", []);
        $current['status'] = 'failed';
        $current['error'] = $exception?->getMessage() ?? 'Cleanup failed';
        Cache::put("scan_status:{$this->jobId}", $current, 3600);

        // Clear the current job marker to allow new scans
        Cache::forget('scan_current_job');
    }
}

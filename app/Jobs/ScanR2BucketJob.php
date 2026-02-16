<?php

declare(strict_types=1);

namespace App\Jobs;

use App\Contracts\MusicStorageInterface;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;

class ScanR2BucketJob implements ShouldQueue
{
    use Dispatchable;
    use InteractsWithQueue;
    use Queueable;
    use SerializesModels;

    /**
     * The number of times the job may be attempted.
     *
     * Scanning is a long-running operation that processes files individually.
     * Individual file failures are handled within the scanner and don't fail the job.
     * We allow 2 tries to handle initial connection issues to R2.
     *
     * @var int
     */
    public $tries = 2;

    /**
     * The maximum number of seconds the job can run.
     *
     * @var int
     */
    public $timeout = 3600;

    /**
     * The number of seconds to wait before retrying the job.
     * Uses longer backoff as this is a heavy operation.
     *
     * @var array<int, int>
     */
    public $backoff = [120];

    /**
     * Number of files to process per batch job.
     */
    private const BATCH_SIZE = 100;

    /**
     * Create a new job instance.
     */
    public function __construct(
        public string $jobId,
        public bool $force = false,
    ) {}

    /**
     * Execute the job.
     */
    public function handle(MusicStorageInterface $storage): void
    {
        try {
            $this->updateStatus('in_progress');

            $storageDriver = config('muzakily.storage.driver', 'r2');
            $bucket = $storageDriver === 'local' ? 'local' : (string) config('filesystems.disks.r2.bucket');
            $extensions = config('muzakily.scanning.extensions', ['mp3', 'aac', 'm4a', 'flac']);

            $scanStartedAt = now();

            // Collect files and create batch jobs (streaming to avoid memory buildup)
            $this->updateProgress(['status' => 'collecting']);
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

                if ($totalFiles % 1000 === 0) {
                    $this->updateProgress([
                        'status' => 'collecting',
                        'collected' => $matchedFiles,
                        'total_scanned' => $totalFiles,
                    ]);
                }
            }

            // Don't forget remaining files
            if (!empty($batch)) {
                $jobs[] = new ScanFileBatchJob($batch, $bucket, $this->force);
            }

            $this->updateProgress([
                'total_files' => $matchedFiles,
                'status' => 'dispatching',
            ]);

            if (empty($jobs)) {
                $this->runCleanup($bucket, $scanStartedAt);
                $this->updateStatus('completed');
                return;
            }

            $jobId = $this->jobId;
            $scanStartedAtString = $scanStartedAt->toIso8601String();

            Bus::batch($jobs)
                ->name("scan-{$this->jobId}")
                ->allowFailures()
                ->progress(function () use ($jobId) {
                    $current = Cache::get("scan_status:{$jobId}", []);
                    $current['progress']['status'] = 'scanning';
                    Cache::put("scan_status:{$jobId}", $current, 3600);
                })
                ->finally(function () use ($bucket, $scanStartedAtString, $jobId) {
                    // Run cleanup and mark complete
                    ScanR2BucketCleanupJob::dispatch($jobId, $bucket, $scanStartedAtString);
                })
                ->onQueue('default')
                ->dispatch();

            $this->updateProgress([
                'status' => 'scanning',
                'total_files' => $matchedFiles,
                'batches' => count($jobs),
            ]);

        } catch (\Throwable $e) {
            $this->updateStatus('failed', $e->getMessage());
            throw $e;
        }
    }

    /**
     * Run cleanup for empty file list case.
     */
    private function runCleanup(string $bucket, \DateTimeInterface $scanStartedAt): void
    {
        $staleEntries = \App\Models\ScanCache::stale($bucket, $scanStartedAt)->get();

        foreach ($staleEntries as $entry) {
            \Illuminate\Support\Facades\DB::transaction(function () use ($entry) {
                $song = \App\Models\Song::findByStoragePath($entry->object_key);
                if ($song) {
                    $song->tags()->detach();
                    $song->forceDelete();
                }
                $entry->delete();
            });
        }

        \App\Models\Tag::chunk(100, function ($tags) {
            $tags->each->updateSongCount();
        });
    }

    /**
     * Update the scan status.
     */
    private function updateStatus(string $status, ?string $error = null): void
    {
        $current = Cache::get("scan_status:{$this->jobId}", []);

        $current['status'] = $status;

        if ($status === 'completed') {
            $current['completed_at'] = now()->toIso8601String();
        }

        if ($error) {
            $current['error'] = $error;
        }

        Cache::put("scan_status:{$this->jobId}", $current, 3600);
    }

    /**
     * Update the scan progress.
     *
     * @param array{total_files?: int, scanned_files?: int, new_songs?: int, updated_songs?: int, errors?: int} $progress
     */
    private function updateProgress(array $progress): void
    {
        $current = Cache::get("scan_status:{$this->jobId}", []);

        $current['progress'] = array_merge($current['progress'] ?? [], $progress);

        Cache::put("scan_status:{$this->jobId}", $current, 3600);
    }

    /**
     * Handle a job failure.
     */
    public function failed(\Throwable $exception): void
    {
        Log::error('ScanR2BucketJob failed permanently', [
            'job_id' => $this->jobId,
            'force' => $this->force,
            'exception' => $exception->getMessage(),
            'attempts' => $this->attempts(),
        ]);

        $this->updateStatus('failed', $exception->getMessage());
    }
}

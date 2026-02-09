<?php

declare(strict_types=1);

namespace App\Jobs;

use App\Services\Library\LibraryScannerService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Cache;

class ScanR2BucketJob implements ShouldQueue
{
    use Dispatchable;
    use InteractsWithQueue;
    use Queueable;
    use SerializesModels;

    /**
     * The number of times the job may be attempted.
     *
     * @var int
     */
    public $tries = 1;

    /**
     * The maximum number of seconds the job can run.
     *
     * @var int
     */
    public $timeout = 3600;

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
    public function handle(LibraryScannerService $scanner): void
    {
        try {
            $this->updateStatus('in_progress');

            $scanner->scan(
                force: $this->force,
                onProgress: fn (array $progress) => $this->updateProgress($progress),
            );

            $this->updateStatus('completed');
        } catch (\Throwable $e) {
            $this->updateStatus('failed', $e->getMessage());
            throw $e;
        } finally {
            // Clear current job after some delay
            Cache::put('scan_current_job', null, 60);
        }
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
}

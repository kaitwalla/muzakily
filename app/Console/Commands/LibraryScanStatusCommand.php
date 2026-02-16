<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Jobs\LibraryScanJob;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Facades\DB;

class LibraryScanStatusCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'library:scan:status
        {--clear : Clear the scan status}
        {--cancel : Cancel any running scan}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Check the status of the library scan job';

    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        if ($this->option('cancel')) {
            return $this->cancelScan();
        }

        if ($this->option('clear')) {
            LibraryScanJob::clearStatus();
            $this->info('Scan status cleared.');
            return Command::SUCCESS;
        }

        $status = LibraryScanJob::getStatus();

        if (!$status) {
            $this->info('No scan status found. Run "php artisan library:scan" to start a scan.');
            return Command::SUCCESS;
        }

        $this->info("Status: {$status['status']}");
        $this->info("Message: {$status['message']}");
        $this->info("Updated: {$status['updated_at']}");

        if (!empty($status['stats'])) {
            $this->newLine();
            $this->info('Statistics:');

            // Filter out batch_id from display stats
            $displayStats = collect($status['stats'])->except('batch_id');

            $this->table(
                ['Metric', 'Value'],
                $displayStats->map(fn ($value, $key) => [
                    str_replace('_', ' ', ucfirst($key)),
                    $value,
                ])->toArray()
            );

            // Show batch progress if available
            if (!empty($status['stats']['batch_id'])) {
                $this->displayBatchProgress($status['stats']['batch_id']);
            }
        }

        return Command::SUCCESS;
    }

    /**
     * Cancel any running scan.
     */
    private function cancelScan(): int
    {
        $cancelled = 0;

        // Try to cancel batch from current status
        $status = LibraryScanJob::getStatus();
        if (!empty($status['stats']['batch_id'])) {
            $batch = Bus::findBatch($status['stats']['batch_id']);
            if ($batch && !$batch->cancelled()) {
                $batch->cancel();
                $this->info("Cancelled batch: {$batch->id}");
                $cancelled++;
            }
        }

        // Also find any unfinished library-scan batches
        $pendingBatches = DB::table('job_batches')
            ->whereNull('cancelled_at')
            ->whereNull('finished_at')
            ->where('name', 'like', '%library-scan%')
            ->orWhere('name', 'like', '%scan-%')
            ->get();

        foreach ($pendingBatches as $row) {
            $batch = Bus::findBatch($row->id);
            if ($batch && !$batch->cancelled()) {
                $batch->cancel();
                $this->info("Cancelled batch: {$batch->id} ({$batch->name})");
                $cancelled++;
            }
        }

        // Clear pending scan jobs from queue
        $deletedJobs = DB::table('jobs')
            ->where('payload', 'like', '%ScanFileBatchJob%')
            ->orWhere('payload', 'like', '%LibraryScanJob%')
            ->orWhere('payload', 'like', '%ScanR2BucketJob%')
            ->delete();

        if ($deletedJobs > 0) {
            $this->info("Removed {$deletedJobs} pending scan jobs from queue.");
        }

        // Clear status
        LibraryScanJob::clearStatus();

        if ($cancelled > 0 || $deletedJobs > 0) {
            $this->info('Scan cancelled successfully.');
        } else {
            $this->info('No running scan found.');
        }

        return Command::SUCCESS;
    }

    /**
     * Display batch progress information.
     */
    private function displayBatchProgress(string $batchId): void
    {
        $batch = Bus::findBatch($batchId);

        if (!$batch) {
            return;
        }

        $this->newLine();
        $this->info('Batch Progress:');

        $completed = $batch->totalJobs - $batch->pendingJobs;
        $percentage = $batch->totalJobs > 0
            ? round(($completed / $batch->totalJobs) * 100, 1)
            : 0;

        $rows = [
            ['Total batches', $batch->totalJobs],
            ['Completed', $completed],
            ['Pending', $batch->pendingJobs],
            ['Failed', $batch->failedJobs],
            ['Progress', "{$percentage}%"],
        ];

        if ($batch->finishedAt) {
            $rows[] = ['Finished at', $batch->finishedAt->format('Y-m-d H:i:s')];
        }

        $this->table(['Metric', 'Value'], $rows);
    }
}

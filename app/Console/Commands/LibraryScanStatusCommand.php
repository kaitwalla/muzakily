<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Jobs\LibraryScanJob;
use Illuminate\Console\Command;

class LibraryScanStatusCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'library:scan:status
        {--clear : Clear the scan status}';

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
            $this->table(
                ['Metric', 'Value'],
                collect($status['stats'])->map(fn ($value, $key) => [
                    str_replace('_', ' ', ucfirst($key)),
                    $value,
                ])->toArray()
            );
        }

        return Command::SUCCESS;
    }
}

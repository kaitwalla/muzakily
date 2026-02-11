<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Services\Library\LibraryScannerService;
use Illuminate\Console\Command;

class LibraryScanCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'library:scan
        {--limit= : Limit number of files to scan}
        {--force : Force re-scan of all files}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Scan the R2 bucket for music files';

    /**
     * Execute the console command.
     */
    public function handle(LibraryScannerService $scanner): int
    {
        $force = $this->option('force');
        $limitOption = $this->option('limit');
        $limit = ($limitOption !== null && $limitOption !== '') ? (int) $limitOption : null;

        $this->info('Starting library scan...');

        if ($force) {
            $this->info('Force mode enabled - re-scanning all files');
        }

        if ($limit !== null) {
            $this->info("Limiting scan to {$limit} files");
        }

        $scanner->scan(
            force: $force,
            limit: $limit,
            onProgress: function (array $stats): void {
                $this->output->write("\r");
                $this->output->write(sprintf(
                    'Scanned: %d | New: %d | Updated: %d | Errors: %d',
                    $stats['scanned_files'],
                    $stats['new_songs'],
                    $stats['updated_songs'],
                    $stats['errors']
                ));
            }
        );

        $this->newLine();
        $this->info('Library scan complete!');

        return Command::SUCCESS;
    }
}

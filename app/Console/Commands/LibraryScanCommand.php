<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Jobs\LibraryScanJob;
use App\Services\Library\LibraryScannerService;
use App\Services\Metadata\MetadataAggregatorService;
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
        {--force : Force re-scan of all files}
        {--enrich : Enrich metadata from MusicBrainz after scanning}
        {--sync : Run synchronously instead of queuing}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Scan the R2 bucket for music files';

    /**
     * Execute the console command.
     */
    public function handle(LibraryScannerService $scanner, MetadataAggregatorService $aggregator): int
    {
        $force = $this->option('force');
        $enrich = $this->option('enrich');
        $sync = $this->option('sync');
        $limitOption = $this->option('limit');
        $limit = ($limitOption !== null && $limitOption !== '') ? (int) $limitOption : null;

        // Check if a scan is already running
        if (LibraryScanJob::isRunning()) {
            $this->error('A library scan is already in progress.');
            $status = LibraryScanJob::getStatus();
            if ($status) {
                $this->info("Status: {$status['message']}");
            }
            return Command::FAILURE;
        }

        if ($sync) {
            return $this->runSync($scanner, $aggregator, $force, $limit, $enrich);
        }

        return $this->runQueued($force, $limit, $enrich);
    }

    /**
     * Run the scan synchronously (original behavior).
     */
    private function runSync(
        LibraryScannerService $scanner,
        MetadataAggregatorService $aggregator,
        bool $force,
        ?int $limit,
        bool $enrich
    ): int {
        $this->info('Starting library scan (synchronous mode)...');

        if ($force) {
            $this->info('Force mode enabled - re-scanning all files');
        }

        if ($limit !== null) {
            $this->info("Limiting scan to {$limit} files");
        }

        if ($enrich) {
            $this->info('Metadata enrichment enabled');
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

        // Enrich metadata if requested
        if ($enrich) {
            $this->enrichMetadataSync($aggregator);
        }

        return Command::SUCCESS;
    }

    /**
     * Run enrichment synchronously with chunking.
     */
    private function enrichMetadataSync(MetadataAggregatorService $aggregator): void
    {
        $this->newLine();
        $this->info('Starting metadata enrichment...');

        $total = \App\Models\Song::whereNull('musicbrainz_id')->count();

        if ($total === 0) {
            $this->info('No songs need enrichment.');
            return;
        }

        $enriched = 0;
        $albumsWithCovers = 0;

        $progressBar = $this->output->createProgressBar($total);
        $progressBar->start();

        \App\Models\Song::whereNull('musicbrainz_id')
            ->with(['artist', 'album'])
            ->chunkById(100, function ($songs) use ($aggregator, &$enriched, &$albumsWithCovers, $progressBar) {
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

                    $progressBar->advance();
                }
            });

        $progressBar->finish();
        $this->newLine();
        $this->info("Enriched {$enriched}/{$total} songs, {$albumsWithCovers} albums got covers");
    }

    /**
     * Run the scan as a queued job.
     */
    private function runQueued(bool $force, ?int $limit, bool $enrich): int
    {
        $this->info('Dispatching library scan job to queue...');

        if ($force) {
            $this->info('Force mode enabled - re-scanning all files');
        }

        if ($limit !== null) {
            $this->info("Limiting scan to {$limit} files");
        }

        if ($enrich) {
            $this->info('Metadata enrichment enabled');
        }

        LibraryScanJob::dispatch($force, $limit, $enrich);

        $this->info('Library scan job dispatched successfully.');
        $this->info('Use "php artisan library:scan:status" to check progress.');
        $this->info('Or use "--sync" flag to run synchronously.');

        return Command::SUCCESS;
    }
}

<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Models\Album;
use App\Services\Library\CoverArtService;
use Illuminate\Console\Command;

class AlbumsFixCoversCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'albums:fix-covers
        {--limit= : Limit number of albums to process}
        {--dry-run : Show what would be fixed without making changes}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Download external album cover URLs and store them in R2';

    /**
     * Execute the console command.
     */
    public function handle(CoverArtService $coverArtService): int
    {
        $dryRun = $this->option('dry-run');
        $limitOption = $this->option('limit');
        $limit = ($limitOption !== null && $limitOption !== '') ? (int) $limitOption : null;

        // Find albums with external cover URLs
        $query = Album::query()
            ->whereNotNull('cover')
            ->where(function ($q) {
                $q->where('cover', 'like', 'http://%')
                    ->orWhere('cover', 'like', 'https://%');
            });

        $total = $query->count();

        if ($total === 0) {
            $this->info('No albums with external cover URLs found.');
            return Command::SUCCESS;
        }

        $this->info(sprintf('Found %d albums with external cover URLs.', $total));

        if ($limit !== null) {
            $this->info(sprintf('Processing up to %d albums.', $limit));
        }

        if ($dryRun) {
            $this->warn('Dry run mode - no changes will be made.');
            $displayQuery = clone $query;
            if ($limit !== null) {
                $displayQuery->take($limit);
            }
            $displayQuery->get()->each(function (Album $album) {
                $this->line(sprintf('  Would fix: %s - %s', $album->name, $album->cover));
            });
            return Command::SUCCESS;
        }

        $fixed = 0;
        $failed = 0;
        $processed = 0;

        $progressBar = $this->output->createProgressBar($limit ?? $total);
        $progressBar->start();

        $query->chunkById(50, function ($albums) use ($coverArtService, &$fixed, &$failed, &$processed, $limit, $progressBar) {
            foreach ($albums as $album) {
                if ($limit !== null && $processed >= $limit) {
                    return false;
                }

                /** @var Album $album */
                $originalUrl = $album->cover;

                $storedUrl = $coverArtService->storeFromUrl($album, $originalUrl);

                if ($storedUrl !== null) {
                    $album->update(['cover' => $storedUrl]);
                    $fixed++;
                } else {
                    $failed++;
                }

                $processed++;
                $progressBar->advance();
            }

            if ($limit !== null && $processed >= $limit) {
                return false;
            }
        });

        $progressBar->finish();
        $this->newLine();

        $this->info(sprintf('Fixed: %d, Failed: %d', $fixed, $failed));

        return Command::SUCCESS;
    }
}

<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Models\Artist;
use App\Services\Metadata\ArtistImageService;
use Illuminate\Console\Command;

class ArtistsFetchImagesCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'artists:fetch-images
        {--limit= : Limit number of artists to process}
        {--force : Re-fetch images for artists that already have one}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Fetch artist images from Deezer, TheAudioDB, and Fanart.tv';

    /**
     * Execute the console command.
     */
    public function handle(ArtistImageService $imageService): int
    {
        $force = $this->option('force');
        $limitOption = $this->option('limit');
        $limit = ($limitOption !== null && $limitOption !== '') ? (int) $limitOption : null;

        $this->info('Fetching artist images...');
        $this->info('Sources: Deezer -> TheAudioDB -> Fanart.tv');

        if ($force) {
            $this->info('Force mode enabled - re-fetching all images');
        }

        // Build query
        $query = Artist::query();

        if (!$force) {
            $query->whereNull('image');
        }

        if ($limit !== null) {
            $query->limit($limit);
            $this->info("Limiting to {$limit} artists");
        }

        $artists = $query->get();
        $total = $artists->count();

        if ($total === 0) {
            $this->info('No artists need images.');
            return Command::SUCCESS;
        }

        $this->info("Found {$total} artists to process");

        $fetched = 0;
        $failed = 0;

        $progressBar = $this->output->createProgressBar($total);
        $progressBar->start();

        foreach ($artists as $artist) {
            // When forcing, pass the artist to fetchAndStore which will handle the replacement atomically
            // Don't clear the image first to avoid data loss if fetch fails
            $result = $imageService->fetchAndStore($artist, $force);

            if ($result) {
                $fetched++;
            } else {
                $failed++;
            }

            $progressBar->advance();

            // Small delay to avoid rate limiting
            usleep(100000); // 100ms
        }

        $progressBar->finish();
        $this->newLine(2);

        $this->info('Artist image fetch complete!');
        $this->table(
            ['Metric', 'Count'],
            [
                ['Artists processed', $total],
                ['Images fetched', $fetched],
                ['Not found', $failed],
            ]
        );

        return Command::SUCCESS;
    }
}

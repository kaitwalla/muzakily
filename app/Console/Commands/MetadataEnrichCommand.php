<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Models\Song;
use App\Services\Metadata\MetadataAggregatorService;
use Illuminate\Console\Command;

class MetadataEnrichCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'metadata:enrich
        {--limit= : Limit number of songs to enrich}
        {--force : Re-enrich songs that already have MusicBrainz IDs}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Enrich song metadata from external sources (MusicBrainz)';

    /**
     * Execute the console command.
     */
    public function handle(MetadataAggregatorService $aggregator): int
    {
        $force = $this->option('force');
        $limitOption = $this->option('limit');
        $limit = ($limitOption !== null && $limitOption !== '') ? (int) $limitOption : null;

        $this->info('Starting metadata enrichment...');

        if ($force) {
            $this->info('Force mode enabled - re-enriching all songs');
        }

        // Build query
        $query = Song::query()->with(['artist', 'album']);

        if (!$force) {
            $query->whereNull('musicbrainz_id');
        }

        if ($limit !== null) {
            $query->limit($limit);
            $this->info("Limiting enrichment to {$limit} songs");
        }

        $songs = $query->get();
        $total = $songs->count();

        if ($total === 0) {
            $this->info('No songs to enrich.');
            return Command::SUCCESS;
        }

        $this->info("Found {$total} songs to enrich");

        $enriched = 0;
        $albumsUpdated = 0;
        $errors = 0;

        $progressBar = $this->output->createProgressBar($total);
        $progressBar->start();

        foreach ($songs as $song) {
            try {
                // Clear musicbrainz_id if forcing to allow re-enrichment
                if ($force && $song->musicbrainz_id) {
                    $song->update(['musicbrainz_id' => null]);
                }

                $hadCover = $song->album?->cover !== null;

                $aggregator->enrich($song);

                // Refresh to check if enrichment worked
                $song->refresh();

                if ($song->musicbrainz_id) {
                    $enriched++;
                }

                // Check if album got a cover
                if ($song->album && !$hadCover && $song->album->fresh()?->cover !== null) {
                    $albumsUpdated++;
                }
            } catch (\Throwable $e) {
                $errors++;
                report($e);
            }

            $progressBar->advance();
        }

        $progressBar->finish();
        $this->newLine(2);

        $this->info("Enrichment complete!");
        $this->table(
            ['Metric', 'Count'],
            [
                ['Songs processed', $total],
                ['Songs enriched', $enriched],
                ['Albums with new covers', $albumsUpdated],
                ['Errors', $errors],
            ]
        );

        return Command::SUCCESS;
    }
}

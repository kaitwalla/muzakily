<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Jobs\EnrichMetadataJob;
use App\Models\Song;
use Illuminate\Console\Command;

class MetadataEnrichDispatchCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'metadata:enrich:dispatch
        {--chunk=100 : Number of songs per batch job}
        {--force : Include songs that already have MusicBrainz IDs}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Dispatch queued batch jobs to enrich song metadata from MusicBrainz';

    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        $force = (bool) $this->option('force');
        $chunkSize = max(1, (int) $this->option('chunk'));

        $query = Song::query()->select('id');

        if (!$force) {
            $query->whereNull('musicbrainz_id');
        }

        $ids = $query->pluck('id')->toArray();
        $total = count($ids);

        if ($total === 0) {
            $this->info('No songs need enrichment.');
            return Command::SUCCESS;
        }

        $chunks = array_chunk($ids, $chunkSize);
        $jobCount = count($chunks);

        $this->info("Found {$total} songs — dispatching {$jobCount} jobs (chunk size: {$chunkSize})");

        foreach ($chunks as $chunk) {
            EnrichMetadataJob::dispatch($chunk);
        }

        $this->info("Done. {$jobCount} jobs queued.");

        return Command::SUCCESS;
    }
}

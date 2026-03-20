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
        {--missing-musicbrainz : Target songs missing a MusicBrainz ID instead of incomplete metadata}
        {--force : Include all songs regardless of metadata status}';

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
        $missingMusicbrainz = (bool) $this->option('missing-musicbrainz');

        $query = Song::query()->select('id');

        if (!$force) {
            if ($missingMusicbrainz) {
                $query->whereNull('musicbrainz_id');
            } else {
                $query->where(function ($q) {
                    $q->whereNull('album_id')
                        ->orWhereNull('artist_id')
                        ->orWhere('artist_name', '')
                        ->orWhere('artist_name', 'Unknown')
                        ->orWhere('artist_name', 'Unknown Artist');
                });
            }
        }

        $ids = $query->pluck('id')->toArray();
        $total = count($ids);

        if ($total === 0) {
            $this->info('No songs need enrichment.');
            return Command::SUCCESS;
        }

        $this->info("Found {$total} songs — dispatching {$total} jobs");

        foreach ($ids as $id) {
            EnrichMetadataJob::dispatch($id);
        }

        $this->info("Done. {$total} jobs queued.");

        return Command::SUCCESS;
    }
}

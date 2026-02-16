<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Enums\AudioFormat;
use App\Models\Album;
use App\Models\Artist;
use App\Models\Song;
use App\Services\Library\MetadataExtractorService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;

class RescanSongsCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'songs:rescan
        {--format=all : Audio format to rescan (mp3, aac, flac, or all)}
        {--unknown-only : Only rescan songs with unknown artist}
        {--zero-duration : Only rescan songs with 0 duration}
        {--limit= : Limit number of songs to process}
        {--dry-run : Show what would be done without making changes}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Rescan songs to re-extract metadata from audio files';

    /**
     * Execute the console command.
     */
    public function handle(MetadataExtractorService $extractor): int
    {
        $format = $this->option('format');
        $unknownOnly = $this->option('unknown-only');
        $limitOption = $this->option('limit');
        $limit = ($limitOption !== null && $limitOption !== '') ? (int) $limitOption : null;
        $dryRun = $this->option('dry-run');

        $this->info('Starting song rescan...');

        if ($dryRun) {
            $this->warn('DRY RUN - No changes will be made');
        }

        // Build query
        $query = Song::query();

        if ($format !== 'all') {
            $audioFormat = AudioFormat::tryFrom($format);
            if ($audioFormat === null) {
                $this->error("Invalid format: {$format}. Use mp3, aac, flac, or all.");
                return Command::FAILURE;
            }
            $query->where('audio_format', $audioFormat->value);
            $this->info("Filtering by format: {$format}");
        }

        if ($unknownOnly) {
            $query->where(function ($q) {
                $q->whereNull('artist_name')
                    ->orWhere('artist_name', '')
                    ->orWhere('artist_name', 'Unknown');
            });
            $this->info('Filtering to unknown artists only');
        }

        if ($this->option('zero-duration')) {
            $query->where('length', '<=', 0);
            $this->info('Filtering to zero duration only');
        }

        if ($limit !== null) {
            $query->limit($limit);
            $this->info("Limiting to {$limit} songs");
        }

        $songs = $query->get();
        $total = $songs->count();

        if ($total === 0) {
            $this->info('No songs match the criteria.');
            return Command::SUCCESS;
        }

        $this->info("Found {$total} songs to rescan");

        $updated = 0;
        $skipped = 0;
        $errors = 0;

        $progressBar = $this->output->createProgressBar($total);
        $progressBar->start();

        $r2 = Storage::disk('r2');

        foreach ($songs as $song) {
            try {
                // Download file temporarily
                $stream = $r2->readStream($song->storage_path);
                if ($stream === null) {
                    $this->newLine();
                    $this->warn("  Could not read: {$song->storage_path}");
                    $skipped++;
                    $progressBar->advance();
                    continue;
                }

                $tempFile = tempnam(sys_get_temp_dir(), 'muzakily_');
                if ($tempFile === false) {
                    $this->newLine();
                    $this->error('  Failed to create temp file');
                    $errors++;
                    $progressBar->advance();
                    continue;
                }

                try {
                    file_put_contents($tempFile, $stream);
                    fclose($stream);

                    // Extract metadata
                    $metadata = $extractor->extract($tempFile);

                    $hasNewData = !empty($metadata['artist']) || !empty($metadata['album']) || ($metadata['duration'] > 0 && $song->length <= 0);

                    if (!$hasNewData) {
                        $skipped++;
                        $progressBar->advance();
                        continue;
                    }

                    if ($dryRun) {
                        $this->newLine();
                        $this->info("  Would update: {$song->title}");
                        $this->info("    Artist: {$song->artist_name} -> " . ($metadata['artist'] ?? 'null'));
                        $this->info("    Album: {$song->album_name} -> " . ($metadata['album'] ?? 'null'));
                        $updated++;
                        $progressBar->advance();
                        continue;
                    }

                    // Update song within transaction
                    DB::transaction(function () use ($song, $metadata): void {
                        // Find or create artist
                        $artist = null;
                        if ($metadata['artist'] ?? null) {
                            $artist = Artist::findOrCreateByName($metadata['artist']);
                        }

                        // Find or create album
                        $album = null;
                        if ($metadata['album'] ?? null) {
                            $album = Album::findOrCreateByNameAndArtist($metadata['album'], $artist);
                            if ($metadata['year'] ?? null) {
                                $album->update(['year' => $metadata['year']]);
                            }
                        }

                        // Update song
                        $song->update([
                            'artist_id' => $artist?->id,
                            'album_id' => $album?->id,
                            'artist_name' => $metadata['artist'] ?? $song->artist_name,
                            'album_name' => $metadata['album'] ?? $song->album_name,
                            'title' => $metadata['title'] ?? $song->title,
                            'track' => $metadata['track'] ?? $song->track,
                            'disc' => $metadata['disc'] ?? $song->disc,
                            'year' => $metadata['year'] ?? $song->year,
                            'lyrics' => $metadata['lyrics'] ?? $song->lyrics,
                            'length' => $metadata['duration'] > 0 ? $metadata['duration'] : $song->length,
                        ]);
                    });

                    $updated++;
                } finally {
                    @unlink($tempFile);
                }
            } catch (\Throwable $e) {
                $errors++;
                $this->newLine();
                $this->error("  Error processing {$song->storage_path}: {$e->getMessage()}");
                report($e);
            }

            $progressBar->advance();
        }

        $progressBar->finish();
        $this->newLine(2);

        $this->info('Rescan complete!');
        $this->table(
            ['Metric', 'Count'],
            [
                ['Songs processed', $total],
                ['Songs updated', $updated],
                ['Songs skipped (no new data)', $skipped],
                ['Errors', $errors],
            ]
        );

        return Command::SUCCESS;
    }
}

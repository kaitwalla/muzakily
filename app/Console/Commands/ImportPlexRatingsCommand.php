<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Models\Favorite;
use App\Models\Song;
use App\Models\User;
use Illuminate\Console\Command;

class ImportPlexRatingsCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'import:plex-ratings
        {file : Path to the JSON file exported from Plex (use scripts/export_plex_ratings.py)}
        {--r2-base= : Base path prefix in R2 storage paths (e.g., "music")}
        {--user= : User ID to create favorites for (defaults to first user)}
        {--min-rating=2 : Minimum star rating to import (1-5, default 2)}
        {--dry-run : Preview matches without creating favorites}
        {--debug : Show sample paths to help diagnose matching issues}
        {--diagnose : Analyze why first 5 unmatched songs fail to match}
        {--limit= : Limit number of tracks to process}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Import song ratings from a Plex export file as favorites';

    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        /** @var string $filePath */
        $filePath = $this->argument('file');

        if (!file_exists($filePath)) {
            $this->error("File not found: {$filePath}");
            $this->newLine();
            $this->info('First, export your Plex ratings using the Python script:');
            $this->info('  python scripts/export_plex_ratings.py --url http://PLEX_IP:32400 --token YOUR_TOKEN');
            return Command::FAILURE;
        }

        // Load and parse the JSON file
        $content = file_get_contents($filePath);
        if ($content === false) {
            $this->error("Could not read file: {$filePath}");
            return Command::FAILURE;
        }

        /** @var array<string, mixed>|null $data */
        $data = json_decode($content, true);
        if (!is_array($data) || !isset($data['tracks']) || !is_array($data['tracks'])) {
            $this->error('Invalid JSON format. Expected {"tracks": [...]}');
            return Command::FAILURE;
        }

        /** @var array<int, mixed> $rawTracks */
        $rawTracks = $data['tracks'];

        // Validate track structure
        $requiredKeys = ['title', 'artist', 'path', 'rating'];
        /** @var array<int, array{title: string, artist: string, album: string, path: string, rating: int}> $allTracks */
        $allTracks = array_filter($rawTracks, function ($track) use ($requiredKeys): bool {
            return is_array($track) && count(array_diff($requiredKeys, array_keys($track))) === 0;
        });

        $skippedCount = count($rawTracks) - count($allTracks);
        if ($skippedCount > 0) {
            $this->warn("Skipped {$skippedCount} malformed tracks");
        }

        $exportedAt = isset($data['exported_at']) && is_string($data['exported_at']) ? $data['exported_at'] : 'unknown date';
        $this->info("Loaded export from: " . $exportedAt);
        $this->info("Total valid tracks in file: " . count($allTracks));

        // Get user
        $userId = $this->option('user');
        $user = $userId ? User::find($userId) : User::first();
        if (!$user) {
            $this->error('No user found. Please specify --user or create a user first.');
            return Command::FAILURE;
        }
        $this->info("Creating favorites for user: {$user->email}");

        // Get minimum rating
        $minRatingOption = $this->option('min-rating');
        $minRating = is_string($minRatingOption) ? (int) $minRatingOption : 2;

        if ($minRating < 1 || $minRating > 5) {
            $this->error('min-rating must be between 1 and 5');
            return Command::FAILURE;
        }

        $this->info("Importing songs with rating >= {$minRating} stars");

        // Filter tracks by rating
        $tracks = array_values(array_filter($allTracks, fn ($t) => $t['rating'] >= $minRating));
        $this->info('Tracks matching rating filter: ' . count($tracks));

        // Apply limit if specified
        $limitOption = $this->option('limit');
        if ($limitOption !== null && $limitOption !== '') {
            $limit = (int) $limitOption;
            $tracks = array_slice($tracks, 0, $limit);
            $this->info("Limiting to first {$limit} tracks");
        }

        if (empty($tracks)) {
            $this->warn('No tracks to import.');
            return Command::SUCCESS;
        }

        // Get path options
        $r2Base = $this->option('r2-base');
        $r2Base = is_string($r2Base) ? $r2Base : '';
        $dryRun = $this->option('dry-run');

        // Debug mode: show sample paths to diagnose matching issues
        if ($this->option('debug')) {
            $this->newLine();
            $this->info('=== DEBUG: Path Comparison ===');
            $this->newLine();

            $this->info('Sample paths from Plex export (first 5):');
            foreach (array_slice($tracks, 0, 5) as $i => $track) {
                $relativePath = $track['path'];
                $r2Path = $r2Base ? rtrim($r2Base, '/') . '/' . ltrim($relativePath, '/') : $relativePath;
                $this->line("  [{$i}] Plex path: {$relativePath}");
                $this->line("      R2 path:   {$r2Path}");
            }

            $this->newLine();
            $this->info('Sample storage_path from database (first 5):');
            $dbSongs = Song::limit(5)->pluck('storage_path');
            foreach ($dbSongs as $i => $path) {
                $this->line("  [{$i}] {$path}");
            }

            $this->newLine();
            $this->warn('Compare the paths above to identify the mismatch.');
            $this->info('Common issues:');
            $this->line('  - Missing --strip-prefix when exporting from Plex');
            $this->line('  - Missing or incorrect --r2-base when importing');
            $this->line('  - Leading/trailing slashes');
            $this->newLine();

            if (!$this->confirm('Continue with import?', true)) {
                return Command::SUCCESS;
            }
        }

        if ($dryRun) {
            $this->warn('DRY RUN - No favorites will be created');
        }

        // Match and create favorites
        $matched = 0;
        $created = 0;
        $alreadyFavorited = 0;
        $notFound = [];
        $diagnose = (bool) $this->option('diagnose');
        $diagnosedCount = 0;

        $progressBar = $this->output->createProgressBar(count($tracks));
        $progressBar->start();

        foreach ($tracks as $track) {
            $relativePath = $track['path'];
            $r2Path = $r2Base ? rtrim($r2Base, '/') . '/' . ltrim($relativePath, '/') : $relativePath;

            // Try to find the song by path
            $song = $this->findSong($r2Path, $relativePath, $track);

            if ($song) {
                $matched++;

                if (!$dryRun) {
                    $existing = Favorite::where([
                        'user_id' => $user->id,
                        'favoritable_type' => 'song',
                        'favoritable_id' => $song->id,
                    ])->exists();

                    if ($existing) {
                        $alreadyFavorited++;
                    } else {
                        Favorite::add($user, $song);
                        $created++;
                    }
                }
            } else {
                $notFound[] = [
                    'title' => $track['title'],
                    'artist' => $track['artist'],
                    'path' => $relativePath,
                    'rating' => $track['rating'],
                ];

                // Diagnose why this track didn't match
                if ($diagnose && $diagnosedCount < 5) {
                    $diagnosedCount++;
                    $progressBar->clear();
                    $this->newLine();
                    $this->warn("=== Diagnosing: {$track['title']} by {$track['artist']} ===");
                    $this->diagnoseMismatch($r2Path, $relativePath, $track);
                    $progressBar->display();
                }
            }

            $progressBar->advance();
        }

        $progressBar->finish();
        $this->newLine(2);

        // Summary
        $this->info('Import complete!');
        $this->table(
            ['Metric', 'Count'],
            [
                ['Tracks in file', count($tracks)],
                ['Matched songs', $matched],
                ['Favorites created', $dryRun ? '(dry run)' : $created],
                ['Already favorited', $dryRun ? '(dry run)' : $alreadyFavorited],
                ['Not found', count($notFound)],
            ]
        );

        // Show unmatched tracks if any
        if (!empty($notFound) && $this->option('verbose')) {
            $this->newLine();
            $this->warn('Unmatched tracks:');
            $this->table(
                ['Title', 'Artist', 'Rating', 'Path'],
                array_map(
                    fn ($t) => [$t['title'], $t['artist'], $t['rating'] . ' stars', $t['path']],
                    array_slice($notFound, 0, 20)
                )
            );

            if (count($notFound) > 20) {
                $this->info('... and ' . (count($notFound) - 20) . ' more');
            }
        } elseif (!empty($notFound)) {
            $this->info('Run with -v to see unmatched tracks');
        }

        return Command::SUCCESS;
    }

    /**
     * Find a song by various matching strategies.
     *
     * @param array{title: string, artist: string, album: string} $track
     */
    private function findSong(string $r2Path, string $relativePath, array $track): ?Song
    {
        // Strategy 1: Exact path match
        $song = Song::where('storage_path', $r2Path)->first();
        if ($song) {
            return $song;
        }

        // Strategy 2: Path ends with relative path
        $escapedRelativePath = $this->escapeLikeWildcards($relativePath);
        $song = Song::where('storage_path', 'ilike', '%' . $escapedRelativePath)->first();
        if ($song) {
            return $song;
        }

        // Strategy 3: Filename match
        $filename = pathinfo($relativePath, PATHINFO_BASENAME);
        $escapedFilename = $this->escapeLikeWildcards($filename);
        $song = Song::where('storage_path', 'ilike', '%/' . $escapedFilename)->first();
        if ($song) {
            return $song;
        }

        // Strategy 4: Title + Artist match (case insensitive)
        $song = Song::where('title_normalized', Song::normalizeName($track['title']))
            ->where(function ($query) use ($track) {
                $query->where('artist_name', 'ilike', $track['artist'])
                    ->orWhereHas('artist', function ($q) use ($track) {
                        $q->where('name', 'ilike', $track['artist']);
                    });
            })
            ->first();

        return $song;
    }

    /**
     * Escape SQL LIKE wildcard characters.
     */
    private function escapeLikeWildcards(string $value): string
    {
        return addcslashes($value, '%_\\');
    }

    /**
     * Diagnose why a track didn't match any song.
     *
     * @param array{title: string, artist: string, album: string} $track
     */
    private function diagnoseMismatch(string $r2Path, string $relativePath, array $track): void
    {
        $this->line("  Plex path: {$relativePath}");
        $this->line("  R2 path:   {$r2Path}");
        $this->newLine();

        // Strategy 1: Exact path
        $this->info("  Strategy 1 - Exact path match:");
        $exactCount = Song::where('storage_path', $r2Path)->count();
        $this->line("    Query: storage_path = '{$r2Path}'");
        $this->line("    Result: {$exactCount} matches");

        // Strategy 2: Path suffix
        $this->info("  Strategy 2 - Path suffix match:");
        $escapedRelativePath = $this->escapeLikeWildcards($relativePath);
        $suffixCount = Song::where('storage_path', 'ilike', '%' . $escapedRelativePath)->count();
        $this->line("    Query: storage_path ILIKE '%{$escapedRelativePath}'");
        $this->line("    Result: {$suffixCount} matches");

        // Show similar paths if no match
        if ($suffixCount === 0) {
            $pathParts = explode('/', $relativePath);
            $lastTwoParts = implode('/', array_slice($pathParts, -2));
            $similarPaths = Song::where('storage_path', 'ilike', '%' . $this->escapeLikeWildcards($lastTwoParts) . '%')
                ->limit(3)
                ->pluck('storage_path');
            if ($similarPaths->isNotEmpty()) {
                $this->line("    Similar paths in DB:");
                foreach ($similarPaths as $p) {
                    $this->line("      - {$p}");
                }
            }
        }

        // Strategy 3: Filename
        $this->info("  Strategy 3 - Filename match:");
        $filename = pathinfo($relativePath, PATHINFO_BASENAME);
        $escapedFilename = $this->escapeLikeWildcards($filename);
        $filenameCount = Song::where('storage_path', 'ilike', '%/' . $escapedFilename)->count();
        $this->line("    Filename: {$filename}");
        $this->line("    Result: {$filenameCount} matches");

        // Strategy 4: Title + Artist
        $this->info("  Strategy 4 - Title + Artist match:");
        $normalizedTitle = Song::normalizeName($track['title']);
        $this->line("    Title: '{$track['title']}' -> normalized: '{$normalizedTitle}'");
        $this->line("    Artist: '{$track['artist']}'");

        // Check title matches
        $titleMatches = Song::where('title_normalized', $normalizedTitle)->get(['id', 'title', 'artist_name']);
        $this->line("    Songs with matching title: {$titleMatches->count()}");

        if ($titleMatches->isNotEmpty()) {
            foreach ($titleMatches->take(3) as $s) {
                $this->line("      - '{$s->title}' by '{$s->artist_name}'");
            }
        } else {
            // Show similar titles
            $similarTitles = Song::where('title_normalized', 'ilike', '%' . $normalizedTitle . '%')
                ->limit(3)
                ->get(['title', 'artist_name']);
            if ($similarTitles->isNotEmpty()) {
                $this->line("    Similar titles in DB:");
                foreach ($similarTitles as $s) {
                    $this->line("      - '{$s->title}' by '{$s->artist_name}'");
                }
            }
        }

        $this->newLine();
    }
}

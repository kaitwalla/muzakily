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
        {--dry-run : Preview matches without creating favorites}';

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
        $tracks = array_filter($allTracks, fn ($t) => $t['rating'] >= $minRating);
        $this->info('Tracks matching rating filter: ' . count($tracks));

        if (empty($tracks)) {
            $this->warn('No tracks to import.');
            return Command::SUCCESS;
        }

        // Get path options
        $r2Base = $this->option('r2-base');
        $r2Base = is_string($r2Base) ? $r2Base : '';
        $dryRun = $this->option('dry-run');

        if ($dryRun) {
            $this->warn('DRY RUN - No favorites will be created');
        }

        // Match and create favorites
        $matched = 0;
        $created = 0;
        $alreadyFavorited = 0;
        $notFound = [];

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
}

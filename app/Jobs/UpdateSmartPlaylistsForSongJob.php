<?php

declare(strict_types=1);

namespace App\Jobs;

use App\Models\Playlist;
use App\Models\Song;
use App\Services\Playlist\SmartPlaylistEvaluator;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

/**
 * Incremental update of smart playlists when a song changes.
 *
 * Checks if the song now matches/doesn't match each smart playlist
 * and updates the playlist_song pivot table accordingly.
 */
class UpdateSmartPlaylistsForSongJob implements ShouldQueue, ShouldBeUnique
{
    use Dispatchable;
    use InteractsWithQueue;
    use Queueable;
    use SerializesModels;

    /**
     * The number of times the job may be attempted.
     *
     * @var int
     */
    public int $tries = 3;

    /**
     * The number of seconds after which the job's unique lock will be released.
     *
     * @var int
     */
    public int $uniqueFor = 60;

    /**
     * Create a new job instance.
     */
    public function __construct(
        public Song $song,
        public bool $removing = false
    ) {}

    /**
     * Get the unique ID for the job.
     */
    public function uniqueId(): string
    {
        return $this->song->id;
    }

    /**
     * Execute the job.
     */
    public function handle(SmartPlaylistEvaluator $evaluator): void
    {
        // If the song is being removed (force deleted), just detach from all playlists
        if ($this->removing) {
            Playlist::smart()
                ->whereHas('songs', fn ($q) => $q->where('songs.id', $this->song->id))
                ->each(function (Playlist $playlist) {
                    $playlist->songs()->detach($this->song->id);
                });
            return;
        }

        // Get all smart playlists that have been materialized
        // We only need to update materialized playlists; unmaterialized ones
        // are evaluated dynamically on each request
        $smartPlaylists = Playlist::smart()
            ->whereNotNull('materialized_at')
            ->get();

        foreach ($smartPlaylists as $playlist) {
            $user = $playlist->user;
            $matches = $evaluator->matches($playlist, $this->song, $user);
            $existsInPlaylist = $playlist->songs()
                ->where('songs.id', $this->song->id)
                ->exists();

            if ($matches && !$existsInPlaylist) {
                // Add song to playlist
                $maxPosition = $playlist->songs()->max('playlist_song.position') ?? -1;
                $playlist->songs()->attach($this->song->id, [
                    'position' => $maxPosition + 1,
                    'added_by' => null,
                    'created_at' => now(),
                ]);
            } elseif (!$matches && $existsInPlaylist) {
                // Remove song from playlist
                $playlist->songs()->detach($this->song->id);
            }
        }
    }
}

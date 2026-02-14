<?php

declare(strict_types=1);

namespace App\Jobs;

use App\Models\Playlist;
use App\Models\Song;
use App\Models\User;
use App\Services\Playlist\SmartPlaylistEvaluator;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

/**
 * Update smart playlists when a user favorites/unfavorites a song.
 *
 * Only affects smart playlists owned by the user that have an is_favorite rule.
 */
class UpdateSmartPlaylistsForFavoriteJob implements ShouldQueue
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
     * Create a new job instance.
     */
    public function __construct(
        public User $user,
        public string $songId
    ) {}

    /**
     * Execute the job.
     */
    public function handle(SmartPlaylistEvaluator $evaluator): void
    {
        $song = Song::find($this->songId);
        if (!$song) {
            return;
        }

        // Get smart playlists owned by this user that have is_favorite rules
        // and have been materialized
        $smartPlaylists = Playlist::smart()
            ->where('user_id', $this->user->id)
            ->whereNotNull('materialized_at')
            ->get()
            ->filter(fn (Playlist $playlist) => $this->hasIsFavoriteRule($playlist));

        foreach ($smartPlaylists as $playlist) {
            $matches = $evaluator->matches($playlist, $song, $this->user);
            $existsInPlaylist = $playlist->songs()
                ->where('songs.id', $song->id)
                ->exists();

            if ($matches && !$existsInPlaylist) {
                // Add song to playlist
                $maxPosition = $playlist->songs()->max('playlist_song.position') ?? -1;
                $playlist->songs()->attach($song->id, [
                    'position' => $maxPosition + 1,
                    'added_by' => null,
                    'created_at' => now(),
                ]);
            } elseif (!$matches && $existsInPlaylist) {
                // Remove song from playlist
                $playlist->songs()->detach($song->id);
            }
        }
    }

    /**
     * Check if the playlist has an is_favorite rule.
     */
    private function hasIsFavoriteRule(Playlist $playlist): bool
    {
        if (empty($playlist->rules)) {
            return false;
        }

        foreach ($playlist->rules as $ruleGroup) {
            foreach ($ruleGroup['rules'] as $rule) {
                if ($rule['field'] === 'is_favorite') {
                    return true;
                }
            }
        }

        return false;
    }
}

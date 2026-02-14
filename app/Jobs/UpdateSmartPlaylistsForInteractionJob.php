<?php

declare(strict_types=1);

namespace App\Jobs;

use App\Models\Interaction;
use App\Models\Playlist;
use App\Models\Song;
use App\Services\Playlist\SmartPlaylistEvaluator;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

/**
 * Update smart playlists when play_count or last_played changes.
 *
 * Only affects smart playlists owned by the user that have play_count or last_played rules.
 */
class UpdateSmartPlaylistsForInteractionJob implements ShouldQueue
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
        public Interaction $interaction
    ) {}

    /**
     * Execute the job.
     */
    public function handle(SmartPlaylistEvaluator $evaluator): void
    {
        /** @var Song|null $song */
        $song = $this->interaction->song;
        /** @var \App\Models\User|null $user */
        $user = $this->interaction->user;

        if ($song === null || $user === null) {
            return;
        }

        // Get smart playlists owned by this user that have play_count or last_played rules
        // and have been materialized
        $smartPlaylists = Playlist::smart()
            ->where('user_id', $user->id)
            ->whereNotNull('materialized_at')
            ->get()
            ->filter(fn (Playlist $playlist) => $this->hasInteractionRule($playlist));

        foreach ($smartPlaylists as $playlist) {
            $matches = $evaluator->matches($playlist, $song, $user);
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
     * Check if the playlist has a play_count or last_played rule.
     */
    private function hasInteractionRule(Playlist $playlist): bool
    {
        if (empty($playlist->rules)) {
            return false;
        }

        foreach ($playlist->rules as $ruleGroup) {
            foreach ($ruleGroup['rules'] as $rule) {
                $field = $rule['field'];
                if ($field === 'play_count' || $field === 'last_played') {
                    return true;
                }
            }
        }

        return false;
    }
}

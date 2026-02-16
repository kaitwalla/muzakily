<?php

declare(strict_types=1);

namespace App\Jobs;

use App\Models\Playlist;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Carbon;

/**
 * Periodically refresh smart playlists that haven't been updated recently.
 *
 * This ensures that newly added songs appear in smart playlists even if
 * no incremental update job ran for them.
 */
class RefreshStaleSmartPlaylistsJob implements ShouldQueue
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
    public int $tries = 1;

    /**
     * Create a new job instance.
     *
     * @param int $staleAfterHours Consider playlists stale after this many hours
     */
    public function __construct(
        public int $staleAfterHours = 24
    ) {}

    /**
     * Execute the job.
     */
    public function handle(): void
    {
        $threshold = Carbon::now()->subHours($this->staleAfterHours);

        Playlist::query()
            ->where('is_smart', true)
            ->where(function ($query) use ($threshold) {
                $query->whereNull('materialized_at')
                    ->orWhere('materialized_at', '<', $threshold);
            })
            ->each(function (Playlist $playlist) {
                RefreshSmartPlaylistJob::dispatch($playlist);
            });
    }
}

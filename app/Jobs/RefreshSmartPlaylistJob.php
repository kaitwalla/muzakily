<?php

declare(strict_types=1);

namespace App\Jobs;

use App\Models\Playlist;
use App\Services\Playlist\SmartPlaylistEvaluator;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\DB;

/**
 * Full rematerialization of a smart playlist.
 *
 * Clears existing materialized songs and re-evaluates all rules.
 */
class RefreshSmartPlaylistJob implements ShouldQueue, ShouldBeUnique
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
    public int $uniqueFor = 300;

    /**
     * Create a new job instance.
     */
    public function __construct(
        public Playlist $playlist
    ) {}

    /**
     * Get the unique ID for the job.
     */
    public function uniqueId(): string
    {
        return $this->playlist->id;
    }

    /**
     * Execute the job.
     */
    public function handle(SmartPlaylistEvaluator $evaluator): void
    {
        if (!$this->playlist->is_smart) {
            return;
        }

        $user = $this->playlist->user;

        DB::transaction(function () use ($evaluator, $user) {
            // Clear existing materialized songs
            $this->playlist->songs()->detach();

            // Get matching songs by evaluating rules directly (not using materialized)
            if (empty($this->playlist->rules)) {
                $this->playlist->update(['materialized_at' => now()]);
                return;
            }

            // Get all matching song IDs (always use dynamic evaluation)
            $matchingSongIds = $evaluator->evaluateDynamic($this->playlist, $user)->pluck('id')->all();

            if (count($matchingSongIds) > 0) {
                // Bulk insert with position
                $pivotData = [];
                foreach ($matchingSongIds as $index => $songId) {
                    $pivotData[$songId] = [
                        'position' => $index,
                        'added_by' => null,
                        'created_at' => now(),
                    ];
                }
                $this->playlist->songs()->attach($pivotData);
            }

            // Update materialized_at timestamp
            $this->playlist->update(['materialized_at' => now()]);
        });
    }
}

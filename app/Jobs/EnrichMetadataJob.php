<?php

declare(strict_types=1);

namespace App\Jobs;

use App\Models\Song;
use App\Services\Metadata\MetadataAggregatorService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

class EnrichMetadataJob implements ShouldQueue
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
    public $tries = 3;

    /**
     * The maximum number of seconds the job can run.
     *
     * @var int
     */
    public $timeout = 600;

    /**
     * The number of seconds to wait before retrying the job.
     * Uses exponential backoff for external API rate limiting.
     *
     * @var array<int, int>
     */
    public $backoff = [60, 120, 300];

    /**
     * Create a new job instance.
     */
    public function __construct(
        public string $songId,
    ) {}

    /**
     * Execute the job.
     */
    public function handle(MetadataAggregatorService $aggregator): void
    {
        $song = Song::query()->with(['artist', 'album'])->find($this->songId);

        if ($song === null) {
            return;
        }

        $aggregator->enrich($song);
    }

    /**
     * Handle a job failure.
     */
    public function failed(\Throwable $exception): void
    {
        Log::error('EnrichMetadataJob failed permanently', [
            'song_id' => $this->songId,
            'exception' => $exception->getMessage(),
        ]);
    }
}

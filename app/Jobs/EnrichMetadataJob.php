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
     * Create a new job instance.
     *
     * @param array<string>|null $songIds
     */
    public function __construct(
        public ?array $songIds = null,
    ) {}

    /**
     * Execute the job.
     */
    public function handle(MetadataAggregatorService $aggregator): void
    {
        $query = Song::query()->with(['artist', 'album']);

        if ($this->songIds !== null) {
            $query->whereIn('id', $this->songIds);
        }

        $query->cursor()->each(function (Song $song) use ($aggregator) {
            $aggregator->enrich($song);
        });
    }
}

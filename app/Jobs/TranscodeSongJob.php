<?php

declare(strict_types=1);

namespace App\Jobs;

use App\Models\Song;
use App\Services\Streaming\TranscodingService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

class TranscodeSongJob implements ShouldQueue
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
     * Create a new job instance.
     */
    public function __construct(
        public Song $song,
        public string $format,
        public int $bitrate = 256,
    ) {}

    /**
     * Execute the job.
     */
    public function handle(TranscodingService $transcodingService): void
    {
        $transcodingService->transcodeAndStore($this->song, $this->format, $this->bitrate);
    }
}

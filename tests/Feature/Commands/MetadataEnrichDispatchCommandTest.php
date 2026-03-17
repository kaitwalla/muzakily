<?php

declare(strict_types=1);

namespace Tests\Feature\Commands;

use App\Jobs\EnrichMetadataJob;
use App\Models\Album;
use App\Models\Artist;
use App\Models\Song;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class MetadataEnrichDispatchCommandTest extends TestCase
{
    use RefreshDatabase;

    private Artist $artist;

    private Album $album;

    protected function setUp(): void
    {
        parent::setUp();
        Queue::fake();
        $this->artist = Artist::factory()->create();
        $this->album = Album::factory()->create(['artist_id' => $this->artist->id]);
    }

    private function makeSong(bool $withMbid = false): Song
    {
        return Song::factory()->create([
            'artist_id' => $this->artist->id,
            'album_id' => $this->album->id,
            'musicbrainz_id' => $withMbid ? 'some-mbid' : null,
        ]);
    }

    #[Test]
    public function it_dispatches_no_jobs_when_all_songs_are_enriched(): void
    {
        $this->makeSong(withMbid: true);
        $this->makeSong(withMbid: true);

        $this->artisan('metadata:enrich:dispatch')
            ->assertSuccessful()
            ->expectsOutputToContain('No songs need enrichment');

        Queue::assertNotPushed(EnrichMetadataJob::class);
    }

    #[Test]
    public function it_dispatches_a_single_job_for_a_small_library(): void
    {
        $songs = collect([
            $this->makeSong(),
            $this->makeSong(),
            $this->makeSong(),
        ]);

        $this->artisan('metadata:enrich:dispatch')
            ->assertSuccessful();

        Queue::assertPushed(EnrichMetadataJob::class, 1);

        Queue::assertPushed(EnrichMetadataJob::class, function (EnrichMetadataJob $job) use ($songs) {
            return collect($job->songIds)->sort()->values()->toArray()
                === $songs->pluck('id')->sort()->values()->toArray();
        });
    }

    #[Test]
    public function it_dispatches_multiple_jobs_when_songs_exceed_chunk_size(): void
    {
        for ($i = 0; $i < 5; $i++) {
            $this->makeSong();
        }

        $this->artisan('metadata:enrich:dispatch', ['--chunk' => 2])
            ->assertSuccessful();

        // 5 songs with chunk size 2 = 3 jobs (2 + 2 + 1)
        Queue::assertPushed(EnrichMetadataJob::class, 3);
    }

    #[Test]
    public function it_skips_already_enriched_songs(): void
    {
        $this->makeSong();
        $this->makeSong(withMbid: true);

        $this->artisan('metadata:enrich:dispatch')
            ->assertSuccessful();

        Queue::assertPushed(EnrichMetadataJob::class, 1);
        Queue::assertPushed(EnrichMetadataJob::class, function (EnrichMetadataJob $job) {
            return count($job->songIds) === 1;
        });
    }

    #[Test]
    public function it_includes_enriched_songs_when_force_flag_is_used(): void
    {
        $this->makeSong();
        $this->makeSong(withMbid: true);

        $this->artisan('metadata:enrich:dispatch', ['--force' => true])
            ->assertSuccessful();

        Queue::assertPushed(EnrichMetadataJob::class, 1);
        Queue::assertPushed(EnrichMetadataJob::class, function (EnrichMetadataJob $job) {
            return count($job->songIds) === 2;
        });
    }

    #[Test]
    public function it_respects_the_chunk_option(): void
    {
        for ($i = 0; $i < 10; $i++) {
            $this->makeSong();
        }

        $this->artisan('metadata:enrich:dispatch', ['--chunk' => 3])
            ->assertSuccessful();

        // 10 songs / 3 per chunk = 4 jobs (3 + 3 + 3 + 1)
        Queue::assertPushed(EnrichMetadataJob::class, 4);
    }

    #[Test]
    public function it_outputs_job_count_summary(): void
    {
        for ($i = 0; $i < 5; $i++) {
            $this->makeSong();
        }

        $this->artisan('metadata:enrich:dispatch', ['--chunk' => 2])
            ->assertSuccessful()
            ->expectsOutputToContain('5 songs')
            ->expectsOutputToContain('3 jobs');
    }
}

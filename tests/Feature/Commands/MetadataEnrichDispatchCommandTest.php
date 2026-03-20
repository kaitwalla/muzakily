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

    private function makeSong(bool $withMbid = false, bool $incomplete = true): Song
    {
        return Song::factory()->create([
            'artist_id' => $this->artist->id,
            'album_id' => $incomplete ? null : $this->album->id,
            'musicbrainz_id' => $withMbid ? 'some-mbid' : null,
        ]);
    }

    #[Test]
    public function it_dispatches_no_jobs_when_all_songs_are_enriched(): void
    {
        $this->makeSong(withMbid: true, incomplete: false);
        $this->makeSong(withMbid: true, incomplete: false);

        $this->artisan('metadata:enrich:dispatch')
            ->assertSuccessful()
            ->expectsOutputToContain('No songs need enrichment');

        Queue::assertNotPushed(EnrichMetadataJob::class);
    }

    #[Test]
    public function it_dispatches_one_job_per_song(): void
    {
        $songs = collect([
            $this->makeSong(),
            $this->makeSong(),
            $this->makeSong(),
        ]);

        $this->artisan('metadata:enrich:dispatch')
            ->assertSuccessful();

        Queue::assertPushed(EnrichMetadataJob::class, 3);

        $songs->each(function (Song $song) {
            Queue::assertPushed(EnrichMetadataJob::class, function (EnrichMetadataJob $job) use ($song) {
                return $job->songId === $song->id;
            });
        });
    }

    #[Test]
    public function it_skips_already_enriched_songs(): void
    {
        $unenriched = $this->makeSong();
        $this->makeSong(withMbid: true, incomplete: false);

        $this->artisan('metadata:enrich:dispatch')
            ->assertSuccessful();

        Queue::assertPushed(EnrichMetadataJob::class, 1);
        Queue::assertPushed(EnrichMetadataJob::class, function (EnrichMetadataJob $job) use ($unenriched) {
            return $job->songId === $unenriched->id;
        });
    }

    #[Test]
    public function it_includes_enriched_songs_when_force_flag_is_used(): void
    {
        $this->makeSong();
        $this->makeSong(withMbid: true, incomplete: false);

        $this->artisan('metadata:enrich:dispatch', ['--force' => true])
            ->assertSuccessful();

        Queue::assertPushed(EnrichMetadataJob::class, 2);
    }

    #[Test]
    public function it_outputs_job_count_summary(): void
    {
        for ($i = 0; $i < 5; $i++) {
            $this->makeSong();
        }

        $this->artisan('metadata:enrich:dispatch')
            ->assertSuccessful()
            ->expectsOutputToContain('5 songs')
            ->expectsOutputToContain('5 jobs');
    }
}

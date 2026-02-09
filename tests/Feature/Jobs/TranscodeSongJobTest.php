<?php

declare(strict_types=1);

namespace Tests\Feature\Jobs;

use App\Enums\AudioFormat;
use App\Jobs\TranscodeSongJob;
use App\Models\Album;
use App\Models\Artist;
use App\Models\Song;
use App\Models\Transcode;
use App\Services\Streaming\TranscodingService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Mockery;
use Mockery\MockInterface;
use Tests\TestCase;

class TranscodeSongJobTest extends TestCase
{
    use RefreshDatabase;

    private Song $song;

    protected function setUp(): void
    {
        parent::setUp();

        $artist = Artist::factory()->create();
        $album = Album::factory()->create(['artist_id' => $artist->id]);
        $this->song = Song::factory()->create([
            'artist_id' => $artist->id,
            'album_id' => $album->id,
            'audio_format' => AudioFormat::FLAC,
        ]);
    }

    public function test_job_has_correct_properties(): void
    {
        $job = new TranscodeSongJob($this->song, 'mp3', 256);

        $this->assertSame($this->song->id, $job->song->id);
        $this->assertEquals('mp3', $job->format);
        $this->assertEquals(256, $job->bitrate);
    }

    public function test_job_default_bitrate(): void
    {
        $job = new TranscodeSongJob($this->song, 'mp3');

        $this->assertEquals(256, $job->bitrate);
    }

    public function test_job_calls_transcoding_service(): void
    {
        $transcode = Transcode::factory()->make([
            'song_id' => $this->song->id,
            'format' => 'mp3',
            'bitrate' => 256,
        ]);

        $this->mock(TranscodingService::class, function (MockInterface $mock) use ($transcode): void {
            $mock->shouldReceive('transcodeAndStore')
                ->once()
                ->with(
                    Mockery::on(fn ($song) => $song->id === $this->song->id),
                    'mp3',
                    256
                )
                ->andReturn($transcode);
        });

        $job = new TranscodeSongJob($this->song, 'mp3', 256);
        $job->handle(app(TranscodingService::class));
    }

    public function test_job_with_aac_format(): void
    {
        $transcode = Transcode::factory()->make([
            'song_id' => $this->song->id,
            'format' => 'aac',
            'bitrate' => 192,
        ]);

        $this->mock(TranscodingService::class, function (MockInterface $mock) use ($transcode): void {
            $mock->shouldReceive('transcodeAndStore')
                ->once()
                ->with(
                    Mockery::on(fn ($song) => $song->id === $this->song->id),
                    'aac',
                    192
                )
                ->andReturn($transcode);
        });

        $job = new TranscodeSongJob($this->song, 'aac', 192);
        $job->handle(app(TranscodingService::class));
    }

    public function test_job_retry_configuration(): void
    {
        $job = new TranscodeSongJob($this->song, 'mp3', 256);

        $this->assertEquals(3, $job->tries);
        $this->assertEquals(600, $job->timeout);
    }
}

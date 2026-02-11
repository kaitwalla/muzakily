<?php

declare(strict_types=1);

namespace Tests\Unit\Services;

use App\Contracts\MusicStorageInterface;
use App\Enums\AudioFormat;
use App\Jobs\TranscodeSongJob;
use App\Models\Album;
use App\Models\Artist;
use App\Models\Song;
use App\Models\Transcode;
use App\Services\Streaming\TranscodingService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use Mockery;
use Mockery\MockInterface;
use Tests\TestCase;

class TranscodingServiceTest extends TestCase
{
    use RefreshDatabase;

    private TranscodingService $service;
    private MusicStorageInterface&MockInterface $storageMock;
    private Song $song;

    protected function setUp(): void
    {
        parent::setUp();

        $this->storageMock = Mockery::mock(MusicStorageInterface::class);
        $this->service = new TranscodingService($this->storageMock);

        $artist = Artist::factory()->create();
        $album = Album::factory()->create(['artist_id' => $artist->id]);
        $this->song = Song::factory()->create([
            'artist_id' => $artist->id,
            'album_id' => $album->id,
            'audio_format' => AudioFormat::FLAC,
            'storage_path' => 'music/test-song.flac',
        ]);

        Queue::fake();
    }

    public function test_get_stream_url_returns_original_for_original_format(): void
    {
        $expectedUrl = 'https://r2.example.com/presigned/music/test-song.flac';
        $this->storageMock->shouldReceive('getStreamUrl')
            ->once()
            ->with('music/test-song.flac', 3600)
            ->andReturn($expectedUrl);

        $url = $this->service->getStreamUrl($this->song, 'original', 256);

        $this->assertEquals($expectedUrl, $url);
        Queue::assertNothingPushed();
    }

    public function test_get_stream_url_returns_original_when_format_matches(): void
    {
        $expectedUrl = 'https://r2.example.com/presigned/music/test-song.flac';
        $this->storageMock->shouldReceive('getStreamUrl')
            ->once()
            ->with('music/test-song.flac', 3600)
            ->andReturn($expectedUrl);

        $url = $this->service->getStreamUrl($this->song, 'flac', 256);

        $this->assertEquals($expectedUrl, $url);
        Queue::assertNothingPushed();
    }

    public function test_get_stream_url_returns_cached_transcode_if_exists(): void
    {
        $transcode = Transcode::factory()->create([
            'song_id' => $this->song->id,
            'format' => 'mp3',
            'bitrate' => 256,
            'storage_key' => 'transcodes/test/mp3_256.mp3',
        ]);

        $expectedUrl = 'https://r2.example.com/presigned/transcodes/test/mp3_256.mp3';
        $this->storageMock->shouldReceive('getStreamUrl')
            ->once()
            ->with($transcode->storage_key, 3600)
            ->andReturn($expectedUrl);

        $url = $this->service->getStreamUrl($this->song, 'mp3', 256);

        $this->assertEquals($expectedUrl, $url);
        Queue::assertNothingPushed();
    }

    public function test_get_stream_url_queues_job_when_transcode_missing(): void
    {
        $expectedUrl = 'https://r2.example.com/presigned/music/test-song.flac';
        $this->storageMock->shouldReceive('getStreamUrl')
            ->once()
            ->with('music/test-song.flac', 3600)
            ->andReturn($expectedUrl);

        $url = $this->service->getStreamUrl($this->song, 'mp3', 256);

        // Returns original while transcoding
        $this->assertEquals($expectedUrl, $url);

        // Verify job was dispatched
        Queue::assertPushed(TranscodeSongJob::class, function ($job) {
            return $job->song->id === $this->song->id
                && $job->format === 'mp3'
                && $job->bitrate === 256;
        });
    }

    public function test_get_stream_url_queues_job_with_different_bitrate(): void
    {
        // Create transcode at 256 bitrate
        Transcode::factory()->create([
            'song_id' => $this->song->id,
            'format' => 'mp3',
            'bitrate' => 256,
        ]);

        // Request 320 bitrate
        $this->storageMock->shouldReceive('getStreamUrl')
            ->once()
            ->andReturn('https://r2.example.com/original');

        $this->service->getStreamUrl($this->song, 'mp3', 320);

        Queue::assertPushed(TranscodeSongJob::class, function ($job) {
            return $job->bitrate === 320;
        });
    }

    public function test_get_stream_url_for_aac_format(): void
    {
        $this->storageMock->shouldReceive('getStreamUrl')
            ->once()
            ->andReturn('https://r2.example.com/original');

        $this->service->getStreamUrl($this->song, 'aac', 192);

        Queue::assertPushed(TranscodeSongJob::class, function ($job) {
            return $job->format === 'aac' && $job->bitrate === 192;
        });
    }

    public function test_mp3_song_returns_original_for_mp3_request(): void
    {
        $mp3Song = Song::factory()->create([
            'artist_id' => $this->song->artist_id,
            'album_id' => $this->song->album_id,
            'audio_format' => AudioFormat::MP3,
            'storage_path' => 'music/test-song.mp3',
        ]);

        $this->storageMock->shouldReceive('getStreamUrl')
            ->once()
            ->with('music/test-song.mp3', 3600)
            ->andReturn('https://r2.example.com/mp3');

        $this->service->getStreamUrl($mp3Song, 'mp3', 256);

        Queue::assertNothingPushed();
    }
}

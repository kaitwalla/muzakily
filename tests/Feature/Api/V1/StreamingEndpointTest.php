<?php

declare(strict_types=1);

namespace Tests\Feature\Api\V1;

use App\Contracts\MusicStorageInterface;
use App\Enums\AudioFormat;
use App\Jobs\TranscodeSongJob;
use App\Models\Album;
use App\Models\Artist;
use App\Models\Song;
use App\Models\Transcode;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use Mockery\MockInterface;
use Tests\TestCase;

class StreamingEndpointTest extends TestCase
{
    use RefreshDatabase;

    private User $user;
    private Song $song;

    protected function setUp(): void
    {
        parent::setUp();

        $this->user = User::factory()->create();
        $artist = Artist::factory()->create();
        $album = Album::factory()->create(['artist_id' => $artist->id]);
        $this->song = Song::factory()->create([
            'artist_id' => $artist->id,
            'album_id' => $album->id,
            'audio_format' => AudioFormat::FLAC,
            'storage_path' => 'music/test-song.flac',
            'length' => 234.5,
        ]);

        Queue::fake();
    }

    public function test_stream_requires_authentication(): void
    {
        $response = $this->getJson("/api/v1/songs/{$this->song->id}/stream");

        $response->assertUnauthorized();
    }

    public function test_stream_returns_presigned_url(): void
    {
        $this->mock(MusicStorageInterface::class, function (MockInterface $mock): void {
            $mock->shouldReceive('getStreamUrl')
                ->andReturn('https://r2.example.com/presigned/music/test-song.flac');
        });

        $response = $this->actingAs($this->user)->getJson("/api/v1/songs/{$this->song->id}/stream");

        $response->assertOk()
            ->assertJsonStructure([
                'data' => ['url', 'audio_format', 'audio_length'],
            ])
            ->assertJsonPath('data.audio_format', 'flac')
            ->assertJsonPath('data.audio_length', 234.5);
    }

    public function test_stream_with_format_parameter(): void
    {
        $this->mock(MusicStorageInterface::class, function (MockInterface $mock): void {
            $mock->shouldReceive('getStreamUrl')
                ->andReturn('https://r2.example.com/presigned/original');
        });

        $response = $this->actingAs($this->user)
            ->getJson("/api/v1/songs/{$this->song->id}/stream?format=mp3");

        $response->assertOk();

        // Should queue transcoding job since format differs from original
        Queue::assertPushed(TranscodeSongJob::class, function ($job) {
            return $job->format === 'mp3';
        });
    }

    public function test_stream_with_bitrate_parameter(): void
    {
        $this->mock(MusicStorageInterface::class, function (MockInterface $mock): void {
            $mock->shouldReceive('getStreamUrl')
                ->andReturn('https://r2.example.com/presigned/original');
        });

        $response = $this->actingAs($this->user)
            ->getJson("/api/v1/songs/{$this->song->id}/stream?format=mp3&bitrate=320");

        $response->assertOk();

        Queue::assertPushed(TranscodeSongJob::class, function ($job) {
            return $job->format === 'mp3' && $job->bitrate === 320;
        });
    }

    public function test_stream_uses_cached_transcode(): void
    {
        Transcode::factory()->create([
            'song_id' => $this->song->id,
            'format' => 'mp3',
            'bitrate' => 256,
            'storage_key' => 'transcodes/test/mp3_256.mp3',
        ]);

        $this->mock(MusicStorageInterface::class, function (MockInterface $mock): void {
            $mock->shouldReceive('getStreamUrl')
                ->with('transcodes/test/mp3_256.mp3', 3600)
                ->andReturn('https://r2.example.com/presigned/transcodes/test/mp3_256.mp3');
        });

        $response = $this->actingAs($this->user)
            ->getJson("/api/v1/songs/{$this->song->id}/stream?format=mp3&bitrate=256");

        $response->assertOk()
            ->assertJsonPath('data.url', 'https://r2.example.com/presigned/transcodes/test/mp3_256.mp3');

        // Should NOT queue job since transcode exists
        Queue::assertNothingPushed();
    }

    public function test_stream_original_format_no_transcoding(): void
    {
        $this->mock(MusicStorageInterface::class, function (MockInterface $mock): void {
            $mock->shouldReceive('getStreamUrl')
                ->andReturn('https://r2.example.com/presigned/original');
        });

        $response = $this->actingAs($this->user)
            ->getJson("/api/v1/songs/{$this->song->id}/stream?format=original");

        $response->assertOk();

        Queue::assertNothingPushed();
    }

    public function test_stream_same_format_no_transcoding(): void
    {
        $this->mock(MusicStorageInterface::class, function (MockInterface $mock): void {
            $mock->shouldReceive('getStreamUrl')
                ->andReturn('https://r2.example.com/presigned/original');
        });

        $response = $this->actingAs($this->user)
            ->getJson("/api/v1/songs/{$this->song->id}/stream?format=flac");

        $response->assertOk();

        Queue::assertNothingPushed();
    }

    public function test_download_requires_authentication(): void
    {
        $this->mock(MusicStorageInterface::class, function (MockInterface $mock): void {
            // No expectations - should not be called
        });

        $response = $this->getJson("/api/v1/songs/{$this->song->id}/download");

        $response->assertUnauthorized();
    }

    public function test_download_redirects_to_download_url(): void
    {
        $this->mock(MusicStorageInterface::class, function (MockInterface $mock): void {
            $mock->shouldReceive('getDownloadUrl')
                ->withArgs(function (string $key, int $expiry, ?string $filename) {
                    return $key === 'music/test-song.flac'
                        && $expiry === 3600
                        && $filename !== null
                        && str_ends_with($filename, '.flac');
                })
                ->andReturn('https://storage.example.com/music/test-song.flac?download=1');
        });

        $response = $this->actingAs($this->user)
            ->get("/api/v1/songs/{$this->song->id}/download");

        $response->assertRedirect('https://storage.example.com/music/test-song.flac?download=1');
    }

    public function test_stream_returns_404_for_nonexistent_song(): void
    {
        $this->mock(MusicStorageInterface::class, function (MockInterface $mock): void {
            // No expectations - should not be called
        });

        $response = $this->actingAs($this->user)
            ->getJson('/api/v1/songs/00000000-0000-0000-0000-000000000000/stream');

        $response->assertNotFound();
    }
}

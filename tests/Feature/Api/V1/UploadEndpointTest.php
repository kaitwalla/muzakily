<?php

declare(strict_types=1);

namespace Tests\Feature\Api\V1;

use App\Contracts\MusicStorageInterface;
use App\Jobs\ProcessUploadedSongJob;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Storage;
use Mockery;
use Mockery\MockInterface;
use Tests\TestCase;

class UploadEndpointTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;
    private User $user;

    protected function setUp(): void
    {
        parent::setUp();
        $this->admin = User::factory()->admin()->create();
        $this->user = User::factory()->create();
        Queue::fake();
        Storage::fake('local');
    }

    public function test_upload_requires_authentication(): void
    {
        $response = $this->postJson('/api/v1/upload', []);

        $response->assertUnauthorized();
    }

    public function test_upload_requires_admin_role(): void
    {
        $file = UploadedFile::fake()->create('song.mp3', 1024, 'audio/mpeg');

        $response = $this->actingAs($this->user)->postJson('/api/v1/upload', [
            'file' => $file,
        ]);

        $response->assertForbidden();
    }

    public function test_upload_validates_file_required(): void
    {
        $response = $this->actingAs($this->admin)->postJson('/api/v1/upload', []);

        $response->assertUnprocessable()
            ->assertJsonValidationErrors(['file']);
    }

    public function test_upload_validates_max_file_size(): void
    {
        // Create a file larger than 100MB
        $file = UploadedFile::fake()->create('song.mp3', 110 * 1024, 'audio/mpeg');

        $response = $this->actingAs($this->admin)->postJson('/api/v1/upload', [
            'file' => $file,
        ]);

        $response->assertUnprocessable()
            ->assertJsonValidationErrors(['file']);
    }

    public function test_upload_accepts_mp3(): void
    {
        $this->mock(MusicStorageInterface::class, function (MockInterface $mock): void {
            $mock->shouldReceive('upload')
                ->once()
                ->andReturn(true);
        });

        $file = UploadedFile::fake()->create('song.mp3', 1024, 'audio/mpeg');

        $response = $this->actingAs($this->admin)->postJson('/api/v1/upload', [
            'file' => $file,
        ]);

        $response->assertAccepted()
            ->assertJsonStructure([
                'data' => ['job_id', 'status', 'filename'],
            ])
            ->assertJsonPath('data.status', 'processing')
            ->assertJsonPath('data.filename', 'song.mp3');

        Queue::assertPushed(ProcessUploadedSongJob::class);
    }

    public function test_upload_accepts_flac(): void
    {
        $this->mock(MusicStorageInterface::class, function (MockInterface $mock): void {
            $mock->shouldReceive('upload')
                ->once()
                ->andReturn(true);
        });

        $file = UploadedFile::fake()->create('song.flac', 5120, 'audio/flac');

        $response = $this->actingAs($this->admin)->postJson('/api/v1/upload', [
            'file' => $file,
        ]);

        $response->assertAccepted()
            ->assertJsonPath('data.filename', 'song.flac');

        Queue::assertPushed(ProcessUploadedSongJob::class);
    }

    public function test_upload_accepts_m4a(): void
    {
        $this->mock(MusicStorageInterface::class, function (MockInterface $mock): void {
            $mock->shouldReceive('upload')
                ->once()
                ->andReturn(true);
        });

        $file = UploadedFile::fake()->create('song.m4a', 2048, 'audio/mp4');

        $response = $this->actingAs($this->admin)->postJson('/api/v1/upload', [
            'file' => $file,
        ]);

        $response->assertAccepted()
            ->assertJsonPath('data.filename', 'song.m4a');

        Queue::assertPushed(ProcessUploadedSongJob::class);
    }

    public function test_upload_rejects_unsupported_format(): void
    {
        $file = UploadedFile::fake()->create('song.wav', 1024, 'audio/wav');

        $response = $this->actingAs($this->admin)->postJson('/api/v1/upload', [
            'file' => $file,
        ]);

        $response->assertUnprocessable()
            ->assertJsonValidationErrors(['file']);
    }

    public function test_upload_dispatches_processing_job(): void
    {
        $this->mock(MusicStorageInterface::class, function (MockInterface $mock): void {
            $mock->shouldReceive('upload')
                ->once()
                ->andReturn(true);
        });

        $file = UploadedFile::fake()->create('test-song.mp3', 1024, 'audio/mpeg');

        $this->actingAs($this->admin)->postJson('/api/v1/upload', [
            'file' => $file,
        ]);

        Queue::assertPushed(ProcessUploadedSongJob::class, function ($job) {
            return str_contains($job->storagePath, 'uploads/') &&
                   $job->originalFilename === 'test-song.mp3';
        });
    }
}

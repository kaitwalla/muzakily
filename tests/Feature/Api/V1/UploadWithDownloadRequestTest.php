<?php

declare(strict_types=1);

namespace Tests\Feature\Api\V1;

use App\Contracts\MusicStorageInterface;
use App\Enums\DownloadRequestStatus;
use App\Jobs\ProcessUploadedSongJob;
use App\Models\DownloadRequest;
use App\Models\Tag;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Storage;
use Mockery\MockInterface;
use Tests\TestCase;

class UploadWithDownloadRequestTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    protected function setUp(): void
    {
        parent::setUp();
        $this->admin = User::factory()->admin()->create();
        Queue::fake();
        Storage::fake('local');
    }

    public function test_upload_with_valid_download_request_id_passes_it_to_job(): void
    {
        $this->mock(MusicStorageInterface::class, function (MockInterface $mock): void {
            $mock->shouldReceive('upload')->once()->andReturn(true);
        });

        $downloadRequest = DownloadRequest::create([
            'user_id' => $this->admin->id,
            'url' => 'https://tidal.com/browse/track/123456',
            'tag_ids' => [],
            'status' => DownloadRequestStatus::PENDING,
        ]);

        $file = UploadedFile::fake()->create('song.mp3', 1024, 'audio/mpeg');

        $response = $this->actingAs($this->admin)->postJson('/api/v1/upload', [
            'file' => $file,
            'download_request_id' => $downloadRequest->id,
        ]);

        $response->assertAccepted();

        Queue::assertPushed(ProcessUploadedSongJob::class, function (ProcessUploadedSongJob $job) use ($downloadRequest): bool {
            return $job->downloadRequestId === $downloadRequest->id;
        });
    }

    public function test_upload_with_invalid_download_request_id_fails_validation(): void
    {
        $file = UploadedFile::fake()->create('song.mp3', 1024, 'audio/mpeg');

        $response = $this->actingAs($this->admin)->postJson('/api/v1/upload', [
            'file' => $file,
            'download_request_id' => 'ffffffff-ffff-ffff-ffff-ffffffffffff',
        ]);

        $response->assertUnprocessable()
            ->assertJsonValidationErrors(['download_request_id']);
    }

    public function test_upload_without_download_request_id_still_succeeds(): void
    {
        $this->mock(MusicStorageInterface::class, function (MockInterface $mock): void {
            $mock->shouldReceive('upload')->once()->andReturn(true);
        });

        $file = UploadedFile::fake()->create('song.mp3', 1024, 'audio/mpeg');

        $response = $this->actingAs($this->admin)->postJson('/api/v1/upload', [
            'file' => $file,
        ]);

        $response->assertAccepted();

        Queue::assertPushed(ProcessUploadedSongJob::class, function (ProcessUploadedSongJob $job): bool {
            return $job->downloadRequestId === null;
        });
    }
}

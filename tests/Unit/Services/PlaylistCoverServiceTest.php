<?php

declare(strict_types=1);

namespace Tests\Unit\Services;

use App\Models\Playlist;
use App\Models\User;
use App\Services\Metadata\UnsplashService;
use App\Services\Playlist\PlaylistCoverService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;
use Mockery;
use Mockery\MockInterface;
use Tests\TestCase;

class PlaylistCoverServiceTest extends TestCase
{
    use RefreshDatabase;

    private PlaylistCoverService $service;
    private UnsplashService&MockInterface $unsplashServiceMock;
    private User $user;

    protected function setUp(): void
    {
        parent::setUp();

        $this->unsplashServiceMock = Mockery::mock(UnsplashService::class);
        $this->app->instance(UnsplashService::class, $this->unsplashServiceMock);

        $this->service = new PlaylistCoverService($this->unsplashServiceMock);
        $this->user = User::factory()->create();

        Storage::fake('public');
    }

    public function test_fetch_and_store_downloads_and_saves_cover_image(): void
    {
        $playlist = Playlist::factory()->create([
            'user_id' => $this->user->id,
            'is_smart' => true,
            'cover' => null,
        ]);

        $this->unsplashServiceMock
            ->shouldReceive('getRandomPhoto')
            ->once()
            ->andReturn([
                'id' => 'photo123',
                'urls' => [
                    'regular' => 'https://images.unsplash.com/photo-123?w=1080',
                ],
                'links' => [
                    'download_location' => 'https://api.unsplash.com/photos/photo123/download',
                ],
            ]);

        $this->unsplashServiceMock
            ->shouldReceive('trackDownload')
            ->once()
            ->with('https://api.unsplash.com/photos/photo123/download');

        // Mock the HTTP call to download the actual image
        Http::fake([
            'images.unsplash.com/*' => Http::response(
                file_get_contents(base_path('tests/fixtures/test-image.jpg')),
                200,
                ['Content-Type' => 'image/jpeg']
            ),
        ]);

        $result = $this->service->fetchAndStore($playlist);

        $this->assertTrue($result);
        $playlist->refresh();
        $this->assertNotNull($playlist->cover);
        $this->assertStringContainsString('playlist-covers/', $playlist->cover);
        Storage::disk('public')->assertExists($playlist->cover);
    }

    public function test_fetch_and_store_skips_if_cover_exists_without_force(): void
    {
        $playlist = Playlist::factory()->create([
            'user_id' => $this->user->id,
            'is_smart' => true,
            'cover' => 'playlist-covers/existing.jpg',
        ]);

        $this->unsplashServiceMock
            ->shouldNotReceive('getRandomPhoto');

        $result = $this->service->fetchAndStore($playlist, false);

        $this->assertFalse($result);
    }

    public function test_fetch_and_store_replaces_cover_with_force(): void
    {
        Storage::disk('public')->put('playlist-covers/old-cover.jpg', 'old content');

        $playlist = Playlist::factory()->create([
            'user_id' => $this->user->id,
            'is_smart' => true,
            'cover' => 'playlist-covers/old-cover.jpg',
        ]);

        $this->unsplashServiceMock
            ->shouldReceive('getRandomPhoto')
            ->once()
            ->andReturn([
                'id' => 'newphoto',
                'urls' => ['regular' => 'https://images.unsplash.com/new-photo'],
                'links' => ['download_location' => 'https://api.unsplash.com/photos/newphoto/download'],
            ]);

        $this->unsplashServiceMock
            ->shouldReceive('trackDownload')
            ->once();

        Http::fake([
            'images.unsplash.com/*' => Http::response(
                file_get_contents(base_path('tests/fixtures/test-image.jpg')),
                200,
                ['Content-Type' => 'image/jpeg']
            ),
        ]);

        $result = $this->service->fetchAndStore($playlist, true);

        $this->assertTrue($result);
        $playlist->refresh();
        $this->assertNotEquals('playlist-covers/old-cover.jpg', $playlist->cover);
    }

    public function test_fetch_and_store_returns_false_when_unsplash_fails(): void
    {
        $playlist = Playlist::factory()->create([
            'user_id' => $this->user->id,
            'is_smart' => true,
            'cover' => null,
        ]);

        $this->unsplashServiceMock
            ->shouldReceive('getRandomPhoto')
            ->once()
            ->andReturn(null);

        $result = $this->service->fetchAndStore($playlist);

        $this->assertFalse($result);
        $playlist->refresh();
        $this->assertNull($playlist->cover);
    }

    public function test_fetch_and_store_returns_false_when_image_download_fails(): void
    {
        $playlist = Playlist::factory()->create([
            'user_id' => $this->user->id,
            'is_smart' => true,
            'cover' => null,
        ]);

        $this->unsplashServiceMock
            ->shouldReceive('getRandomPhoto')
            ->once()
            ->andReturn([
                'id' => 'photo123',
                'urls' => ['regular' => 'https://images.unsplash.com/photo-123'],
                'links' => ['download_location' => 'https://api.unsplash.com/photos/photo123/download'],
            ]);

        // trackDownload should not be called when image download fails
        $this->unsplashServiceMock
            ->shouldNotReceive('trackDownload');

        Http::fake([
            'images.unsplash.com/*' => Http::response([], 500),
        ]);

        $result = $this->service->fetchAndStore($playlist);

        $this->assertFalse($result);
    }

    public function test_fetch_and_store_prevents_concurrent_operations_with_lock(): void
    {
        $playlist = Playlist::factory()->create([
            'user_id' => $this->user->id,
            'is_smart' => true,
            'cover' => null,
        ]);

        // Simulate the lock is already held
        Cache::lock('playlist-cover:' . $playlist->id, 30)->get();

        $this->unsplashServiceMock
            ->shouldNotReceive('getRandomPhoto');

        $result = $this->service->fetchAndStore($playlist);

        $this->assertFalse($result);
    }

    public function test_fetch_and_store_deletes_old_cover_file_when_replacing(): void
    {
        $oldCoverPath = 'playlist-covers/old-cover.jpg';
        Storage::disk('public')->put($oldCoverPath, 'old content');

        $playlist = Playlist::factory()->create([
            'user_id' => $this->user->id,
            'is_smart' => true,
            'cover' => $oldCoverPath,
        ]);

        $this->unsplashServiceMock
            ->shouldReceive('getRandomPhoto')
            ->once()
            ->andReturn([
                'id' => 'newphoto',
                'urls' => ['regular' => 'https://images.unsplash.com/new-photo'],
                'links' => ['download_location' => 'https://api.unsplash.com/photos/newphoto/download'],
            ]);

        $this->unsplashServiceMock
            ->shouldReceive('trackDownload')
            ->once();

        Http::fake([
            'images.unsplash.com/*' => Http::response(
                file_get_contents(base_path('tests/fixtures/test-image.jpg')),
                200,
                ['Content-Type' => 'image/jpeg']
            ),
        ]);

        $this->service->fetchAndStore($playlist, true);

        Storage::disk('public')->assertMissing($oldCoverPath);
    }

    public function test_fetch_and_store_handles_png_images(): void
    {
        $playlist = Playlist::factory()->create([
            'user_id' => $this->user->id,
            'is_smart' => true,
            'cover' => null,
        ]);

        $this->unsplashServiceMock
            ->shouldReceive('getRandomPhoto')
            ->once()
            ->andReturn([
                'id' => 'photo123',
                'urls' => ['regular' => 'https://images.unsplash.com/photo-123.png'],
                'links' => ['download_location' => 'https://api.unsplash.com/photos/photo123/download'],
            ]);

        $this->unsplashServiceMock
            ->shouldReceive('trackDownload')
            ->once();

        // Create a minimal PNG (1x1 transparent)
        $pngContent = base64_decode('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAYAAAAfFcSJAAAADUlEQVR42mNk+M9QDwADhgGAWjR9awAAAABJRU5ErkJggg==');

        Http::fake([
            'images.unsplash.com/*' => Http::response(
                $pngContent,
                200,
                ['Content-Type' => 'image/png']
            ),
        ]);

        $result = $this->service->fetchAndStore($playlist);

        $this->assertTrue($result);
        $playlist->refresh();
        $this->assertStringEndsWith('.png', $playlist->cover);
    }

    public function test_upload_cover_stores_file_and_updates_playlist(): void
    {
        $playlist = Playlist::factory()->create([
            'user_id' => $this->user->id,
            'cover' => null,
        ]);

        $file = new \Illuminate\Http\UploadedFile(
            base_path('tests/fixtures/test-image.jpg'),
            'cover.jpg',
            'image/jpeg',
            null,
            true
        );

        $result = $this->service->uploadCover($playlist, $file);

        $this->assertTrue($result);
        $playlist->refresh();
        $this->assertNotNull($playlist->cover);
        $this->assertStringContainsString('playlist-covers/', $playlist->cover);
        Storage::disk('public')->assertExists($playlist->cover);
    }

    public function test_upload_cover_deletes_old_cover(): void
    {
        $oldCoverPath = 'playlist-covers/old-cover.jpg';
        Storage::disk('public')->put($oldCoverPath, 'old content');

        $playlist = Playlist::factory()->create([
            'user_id' => $this->user->id,
            'cover' => $oldCoverPath,
        ]);

        $file = new \Illuminate\Http\UploadedFile(
            base_path('tests/fixtures/test-image.jpg'),
            'new-cover.jpg',
            'image/jpeg',
            null,
            true
        );

        $this->service->uploadCover($playlist, $file);

        Storage::disk('public')->assertMissing($oldCoverPath);
    }
}

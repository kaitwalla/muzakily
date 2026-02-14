<?php

declare(strict_types=1);

namespace Tests\Unit\Services;

use App\Services\Metadata\UnsplashService;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class UnsplashServiceTest extends TestCase
{
    private UnsplashService $service;

    protected function setUp(): void
    {
        parent::setUp();

        config([
            'muzakily.metadata.unsplash.enabled' => true,
            'muzakily.metadata.unsplash.access_key' => 'test-access-key',
            'muzakily.metadata.unsplash.collections' => ['123', '456'],
            'muzakily.metadata.unsplash.timeout' => 10,
        ]);

        $this->service = new UnsplashService();
    }

    public function test_get_random_photo_returns_photo_data(): void
    {
        Http::fake([
            'api.unsplash.com/photos/random*' => Http::response([
                'id' => 'abc123',
                'urls' => [
                    'raw' => 'https://images.unsplash.com/photo-abc123',
                    'full' => 'https://images.unsplash.com/photo-abc123?full',
                    'regular' => 'https://images.unsplash.com/photo-abc123?w=1080',
                ],
                'links' => [
                    'download_location' => 'https://api.unsplash.com/photos/abc123/download',
                ],
                'user' => [
                    'name' => 'Test Photographer',
                    'username' => 'testuser',
                ],
            ], 200),
        ]);

        $result = $this->service->getRandomPhoto();

        $this->assertNotNull($result);
        $this->assertEquals('abc123', $result['id']);
        $this->assertArrayHasKey('urls', $result);
        $this->assertArrayHasKey('links', $result);
        $this->assertEquals('https://api.unsplash.com/photos/abc123/download', $result['links']['download_location']);
    }

    public function test_get_random_photo_uses_configured_collections(): void
    {
        Http::fake([
            'api.unsplash.com/photos/random*' => Http::response([
                'id' => 'abc123',
                'urls' => ['regular' => 'https://example.com/photo.jpg'],
                'links' => ['download_location' => 'https://api.unsplash.com/photos/abc123/download'],
            ], 200),
        ]);

        $this->service->getRandomPhoto();

        Http::assertSent(function ($request) {
            $query = $request->data();
            return str_contains($query['collections'] ?? '', '123') &&
                   str_contains($query['collections'] ?? '', '456');
        });
    }

    public function test_get_random_photo_sends_authorization_header(): void
    {
        Http::fake([
            'api.unsplash.com/photos/random*' => Http::response([
                'id' => 'abc123',
                'urls' => ['regular' => 'https://example.com/photo.jpg'],
                'links' => ['download_location' => 'https://api.unsplash.com/photos/abc123/download'],
            ], 200),
        ]);

        $this->service->getRandomPhoto();

        Http::assertSent(function ($request) {
            return $request->hasHeader('Authorization', 'Client-ID test-access-key');
        });
    }

    public function test_get_random_photo_returns_null_on_api_error(): void
    {
        Http::fake([
            'api.unsplash.com/photos/random*' => Http::response([], 500),
        ]);

        $result = $this->service->getRandomPhoto();

        $this->assertNull($result);
    }

    public function test_get_random_photo_returns_null_when_disabled(): void
    {
        config(['muzakily.metadata.unsplash.enabled' => false]);
        $service = new UnsplashService();

        Http::fake();

        $result = $service->getRandomPhoto();

        $this->assertNull($result);
        Http::assertNothingSent();
    }

    public function test_get_random_photo_returns_null_without_access_key(): void
    {
        config(['muzakily.metadata.unsplash.access_key' => null]);
        $service = new UnsplashService();

        Http::fake();

        $result = $service->getRandomPhoto();

        $this->assertNull($result);
        Http::assertNothingSent();
    }

    public function test_get_random_photo_returns_null_on_timeout(): void
    {
        Http::fake([
            'api.unsplash.com/photos/random*' => function () {
                throw new \Illuminate\Http\Client\ConnectionException('Connection timed out');
            },
        ]);

        $result = $this->service->getRandomPhoto();

        $this->assertNull($result);
    }

    public function test_track_download_sends_request_to_download_location(): void
    {
        Http::fake([
            'api.unsplash.com/photos/abc123/download*' => Http::response(['url' => 'https://example.com'], 200),
        ]);

        $this->service->trackDownload('https://api.unsplash.com/photos/abc123/download');

        Http::assertSent(function ($request) {
            return $request->url() === 'https://api.unsplash.com/photos/abc123/download' &&
                   $request->hasHeader('Authorization', 'Client-ID test-access-key');
        });
    }

    public function test_track_download_handles_errors_gracefully(): void
    {
        Http::fake([
            'api.unsplash.com/*' => Http::response([], 500),
        ]);

        // Should not throw an exception
        $this->service->trackDownload('https://api.unsplash.com/photos/abc123/download');

        $this->assertTrue(true);
    }

    public function test_get_random_photo_with_empty_collections(): void
    {
        config(['muzakily.metadata.unsplash.collections' => []]);
        $service = new UnsplashService();

        Http::fake([
            'api.unsplash.com/photos/random*' => Http::response([
                'id' => 'abc123',
                'urls' => ['regular' => 'https://example.com/photo.jpg'],
                'links' => ['download_location' => 'https://api.unsplash.com/photos/abc123/download'],
            ], 200),
        ]);

        $result = $service->getRandomPhoto();

        // Should still work, just without collection filter
        $this->assertNotNull($result);
    }
}

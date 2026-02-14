<?php

declare(strict_types=1);

namespace Tests\Unit\Services\Library;

use App\Models\Album;
use App\Services\Library\CoverArtService;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class CoverArtServiceTest extends TestCase
{
    private CoverArtService $service;

    protected function setUp(): void
    {
        parent::setUp();
        $this->service = new CoverArtService();
        Storage::fake('r2');
        Storage::fake('public');
    }

    #[Test]
    public function store_from_url_downloads_and_stores_image(): void
    {
        config(['muzakily.storage.driver' => 'local']);

        $album = Album::factory()->create(['cover' => null]);

        // Minimal valid JPEG
        $fakeImageContent = base64_decode('/9j/4AAQSkZJRgABAQEASABIAAD/2wBDAAgGBgcGBQgHBwcJCQgKDBQNDAsLDBkSEw8UHRofHh0aHBwgJC4nICIsIxwcKDcpLDAxNDQ0Hyc5PTgyPC4zNDL/2wBDAQkJCQwLDBgNDRgyIRwhMjIyMjIyMjIyMjIyMjIyMjIyMjIyMjIyMjIyMjIyMjIyMjIyMjIyMjIyMjIyMjIyMjL/wAARCAABAAEDASIAAhEBAxEB/8QAFQABAQAAAAAAAAAAAAAAAAAAAAn/xAAUEAEAAAAAAAAAAAAAAAAAAAAA/8QAFQEBAQAAAAAAAAAAAAAAAAAAAAX/xAAUEQEAAAAAAAAAAAAAAAAAAAAA/9oADAMBEQCEAwEPwAAf/9k=');

        Http::fake([
            'https://coverartarchive.org/*' => Http::response($fakeImageContent, 200, [
                'Content-Type' => 'image/jpeg',
            ]),
        ]);

        $url = 'https://coverartarchive.org/release/abc123/front-250';
        $result = $this->service->storeFromUrl($album, $url);

        $this->assertNotNull($result);
        $this->assertStringContainsString('covers/' . $album->uuid, $result);
        Storage::disk('public')->assertExists('covers/' . $album->uuid . '.jpg');
    }

    #[Test]
    public function store_from_url_returns_null_on_failed_download(): void
    {
        config(['muzakily.storage.driver' => 'local']);

        $album = Album::factory()->create(['cover' => null]);

        Http::fake([
            '*' => Http::response('Not Found', 404),
        ]);

        $url = 'https://coverartarchive.org/release/invalid/front-250';
        $result = $this->service->storeFromUrl($album, $url);

        $this->assertNull($result);
    }

    #[Test]
    public function store_from_url_handles_png_images(): void
    {
        config(['muzakily.storage.driver' => 'local']);

        $album = Album::factory()->create(['cover' => null]);

        // Minimal valid PNG
        $pngContent = base64_decode('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAYAAAAfFcSJAAAADUlEQVR42mNk+M9QDwADhgGAWjR9awAAAABJRU5ErkJggg==');

        Http::fake([
            '*' => Http::response($pngContent, 200, [
                'Content-Type' => 'image/png',
            ]),
        ]);

        $url = 'https://example.com/cover.png';
        $result = $this->service->storeFromUrl($album, $url);

        $this->assertNotNull($result);
        Storage::disk('public')->assertExists('covers/' . $album->uuid . '.png');
    }

    #[Test]
    public function is_external_url_detects_external_covers(): void
    {
        $this->assertTrue($this->service->isExternalUrl('https://coverartarchive.org/release/abc/front'));
        $this->assertTrue($this->service->isExternalUrl('http://example.com/image.jpg'));
        $this->assertFalse($this->service->isExternalUrl('covers/abc123.jpg'));
        $this->assertFalse($this->service->isExternalUrl(null));
    }

    #[Test]
    public function store_from_url_blocks_localhost_urls(): void
    {
        config(['muzakily.storage.driver' => 'local']);

        $album = Album::factory()->create(['cover' => null]);

        Http::fake([
            '*' => Http::response('should not be called', 200),
        ]);

        // These should all be blocked without making HTTP requests
        $this->assertNull($this->service->storeFromUrl($album, 'http://localhost/image.jpg'));
        $this->assertNull($this->service->storeFromUrl($album, 'http://127.0.0.1/image.jpg'));
        $this->assertNull($this->service->storeFromUrl($album, 'file:///etc/passwd'));

        Http::assertNothingSent();
    }
}

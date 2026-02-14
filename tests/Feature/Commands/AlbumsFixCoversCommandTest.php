<?php

declare(strict_types=1);

namespace Tests\Feature\Commands;

use App\Models\Album;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class AlbumsFixCoversCommandTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake('r2');
        Storage::fake('public');
        Http::preventStrayRequests();
    }

    #[Test]
    public function it_fixes_albums_with_external_cover_urls(): void
    {
        config(['muzakily.storage.driver' => 'local']);

        // Create album with external cover URL
        $album = Album::factory()->create([
            'cover' => 'https://coverartarchive.org/release/abc123/front-250',
        ]);

        // Minimal valid JPEG
        $fakeImageContent = base64_decode('/9j/4AAQSkZJRgABAQEASABIAAD/2wBDAAgGBgcGBQgHBwcJCQgKDBQNDAsLDBkSEw8UHRofHh0aHBwgJC4nICIsIxwcKDcpLDAxNDQ0Hyc5PTgyPC4zNDL/2wBDAQkJCQwLDBgNDRgyIRwhMjIyMjIyMjIyMjIyMjIyMjIyMjIyMjIyMjIyMjIyMjIyMjIyMjIyMjIyMjIyMjIyMjL/wAARCAABAAEDASIAAhEBAxEB/8QAFQABAQAAAAAAAAAAAAAAAAAAAAn/xAAUEAEAAAAAAAAAAAAAAAAAAAAA/8QAFQEBAQAAAAAAAAAAAAAAAAAAAAX/xAAUEQEAAAAAAAAAAAAAAAAAAAAA/9oADAMBEQCEAwEPwAAf/9k=');

        Http::fake([
            '*' => Http::response($fakeImageContent, 200, [
                'Content-Type' => 'image/jpeg',
            ]),
        ]);

        $this->artisan('albums:fix-covers')
            ->assertSuccessful();

        $album->refresh();
        // Cover should now be a local URL, not an external one
        $this->assertStringNotContainsString('coverartarchive.org', $album->cover);
        $this->assertStringContainsString('covers/', $album->cover);
    }

    #[Test]
    public function it_skips_albums_with_local_covers(): void
    {
        // Create album with local cover path (not an external URL)
        $album = Album::factory()->create([
            'cover' => 'covers/local-cover.jpg',
        ]);

        Http::fake([]);

        $this->artisan('albums:fix-covers')
            ->assertSuccessful()
            ->expectsOutput('No albums with external cover URLs found.');

        // Cover should remain unchanged
        $album->refresh();
        $this->assertEquals('covers/local-cover.jpg', $album->cover);
    }

    #[Test]
    public function it_reports_no_albums_when_none_have_external_urls(): void
    {
        // Create album with no cover
        Album::factory()->create(['cover' => null]);

        Http::fake([]);

        $this->artisan('albums:fix-covers')
            ->assertSuccessful()
            ->expectsOutput('No albums with external cover URLs found.');
    }

    #[Test]
    public function it_respects_limit_option(): void
    {
        config(['muzakily.storage.driver' => 'local']);

        // Create multiple albums with external covers
        Album::factory()->count(5)->create([
            'cover' => 'https://coverartarchive.org/release/abc123/front-250',
        ]);

        $fakeImageContent = base64_decode('/9j/4AAQSkZJRgABAQEASABIAAD/2wBDAAgGBgcGBQgHBwcJCQgKDBQNDAsLDBkSEw8UHRofHh0aHBwgJC4nICIsIxwcKDcpLDAxNDQ0Hyc5PTgyPC4zNDL/wAARCAABAAEDASIAAhEBAxEB/8QAFQABAQAAAAAAAAAAAAAAAAAAAAn/xAAUEAEAAAAAAAAAAAAAAAAAAAAA/9oADAMBEQCEAwEPwAAf/9k=');

        Http::fake([
            '*' => Http::response($fakeImageContent, 200, [
                'Content-Type' => 'image/jpeg',
            ]),
        ]);

        $this->artisan('albums:fix-covers', ['--limit' => 2])
            ->assertSuccessful();

        // Should have exactly 2 HTTP requests
        Http::assertSentCount(2);
    }

    #[Test]
    public function dry_run_does_not_modify_albums(): void
    {
        $album = Album::factory()->create([
            'cover' => 'https://coverartarchive.org/release/abc123/front-250',
        ]);

        Http::fake([]);

        $this->artisan('albums:fix-covers', ['--dry-run' => true])
            ->assertSuccessful();

        // No HTTP requests should be made in dry-run mode
        Http::assertNothingSent();

        // Album should remain unchanged
        $album->refresh();
        $this->assertEquals('https://coverartarchive.org/release/abc123/front-250', $album->cover);
    }
}

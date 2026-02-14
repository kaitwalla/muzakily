<?php

declare(strict_types=1);

namespace Tests\Feature\Api\V1;

use App\Models\Playlist;
use App\Models\User;
use App\Services\Metadata\UnsplashService;
use App\Services\Playlist\PlaylistCoverService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;
use Mockery;
use Mockery\MockInterface;
use Tests\TestCase;

class PlaylistCoverEndpointTest extends TestCase
{
    use RefreshDatabase;

    private User $user;

    protected function setUp(): void
    {
        parent::setUp();
        $this->user = User::factory()->create();
        Storage::fake('public');
    }

    public function test_refresh_cover_requires_authentication(): void
    {
        $playlist = Playlist::factory()->create([
            'user_id' => $this->user->id,
            'is_smart' => true,
        ]);

        $response = $this->postJson("/api/v1/playlists/{$playlist->id}/refresh-cover");

        $response->assertUnauthorized();
    }

    public function test_refresh_cover_returns_404_for_nonexistent_playlist(): void
    {
        $response = $this->actingAs($this->user)
            ->postJson('/api/v1/playlists/00000000-0000-0000-0000-000000000000/refresh-cover');

        $response->assertNotFound();
    }

    public function test_refresh_cover_returns_403_for_other_users_playlist(): void
    {
        $otherUser = User::factory()->create();
        $playlist = Playlist::factory()->create([
            'user_id' => $otherUser->id,
            'is_smart' => true,
        ]);

        $response = $this->actingAs($this->user)
            ->postJson("/api/v1/playlists/{$playlist->id}/refresh-cover");

        $response->assertForbidden();
    }

    public function test_refresh_cover_returns_422_for_regular_playlist(): void
    {
        $playlist = Playlist::factory()->create([
            'user_id' => $this->user->id,
            'is_smart' => false,
        ]);

        $response = $this->actingAs($this->user)
            ->postJson("/api/v1/playlists/{$playlist->id}/refresh-cover");

        $response->assertUnprocessable()
            ->assertJsonPath('error.code', 'INVALID_OPERATION')
            ->assertJsonPath('error.message', 'Cover refresh is only available for smart playlists');
    }

    public function test_refresh_cover_fetches_new_cover_for_smart_playlist(): void
    {
        $playlist = Playlist::factory()->create([
            'user_id' => $this->user->id,
            'is_smart' => true,
            'cover' => null,
        ]);

        /** @var UnsplashService&MockInterface $unsplashMock */
        $unsplashMock = Mockery::mock(UnsplashService::class);
        $unsplashMock->shouldReceive('getRandomPhoto')
            ->once()
            ->andReturn([
                'id' => 'test-photo',
                'urls' => ['regular' => 'https://images.unsplash.com/test-photo'],
                'links' => ['download_location' => 'https://api.unsplash.com/photos/test-photo/download'],
            ]);
        $unsplashMock->shouldReceive('trackDownload')->once();
        $this->app->instance(UnsplashService::class, $unsplashMock);

        Http::fake([
            'images.unsplash.com/*' => Http::response(
                file_get_contents(base_path('tests/fixtures/test-image.jpg')),
                200,
                ['Content-Type' => 'image/jpeg']
            ),
        ]);

        $response = $this->actingAs($this->user)
            ->postJson("/api/v1/playlists/{$playlist->id}/refresh-cover");

        $response->assertOk()
            ->assertJsonStructure([
                'data' => ['id', 'name', 'cover_url'],
            ]);

        $playlist->refresh();
        $this->assertNotNull($playlist->cover);
        $this->assertNotNull($response->json('data.cover_url'));
    }

    public function test_refresh_cover_replaces_existing_cover(): void
    {
        $oldCoverPath = 'playlist-covers/old-cover.jpg';
        Storage::disk('public')->put($oldCoverPath, 'old content');

        $playlist = Playlist::factory()->create([
            'user_id' => $this->user->id,
            'is_smart' => true,
            'cover' => $oldCoverPath,
        ]);

        /** @var UnsplashService&MockInterface $unsplashMock */
        $unsplashMock = Mockery::mock(UnsplashService::class);
        $unsplashMock->shouldReceive('getRandomPhoto')
            ->once()
            ->andReturn([
                'id' => 'new-photo',
                'urls' => ['regular' => 'https://images.unsplash.com/new-photo'],
                'links' => ['download_location' => 'https://api.unsplash.com/photos/new-photo/download'],
            ]);
        $unsplashMock->shouldReceive('trackDownload')->once();
        $this->app->instance(UnsplashService::class, $unsplashMock);

        Http::fake([
            'images.unsplash.com/*' => Http::response(
                file_get_contents(base_path('tests/fixtures/test-image.jpg')),
                200,
                ['Content-Type' => 'image/jpeg']
            ),
        ]);

        $response = $this->actingAs($this->user)
            ->postJson("/api/v1/playlists/{$playlist->id}/refresh-cover");

        $response->assertOk();

        $playlist->refresh();
        $this->assertNotEquals($oldCoverPath, $playlist->cover);
        Storage::disk('public')->assertMissing($oldCoverPath);
    }

    public function test_refresh_cover_returns_500_when_service_fails(): void
    {
        $playlist = Playlist::factory()->create([
            'user_id' => $this->user->id,
            'is_smart' => true,
            'cover' => null,
        ]);

        /** @var UnsplashService&MockInterface $unsplashMock */
        $unsplashMock = Mockery::mock(UnsplashService::class);
        $unsplashMock->shouldReceive('getRandomPhoto')
            ->once()
            ->andReturn(null);
        $this->app->instance(UnsplashService::class, $unsplashMock);

        $response = $this->actingAs($this->user)
            ->postJson("/api/v1/playlists/{$playlist->id}/refresh-cover");

        $response->assertStatus(500)
            ->assertJsonPath('error.code', 'COVER_FETCH_FAILED')
            ->assertJsonPath('error.message', 'Failed to fetch a new cover image');
    }

    // Upload cover tests

    public function test_upload_cover_requires_authentication(): void
    {
        $playlist = Playlist::factory()->create([
            'user_id' => $this->user->id,
        ]);

        $file = new \Illuminate\Http\UploadedFile(
            base_path('tests/fixtures/test-image.jpg'),
            'cover.jpg',
            'image/jpeg',
            null,
            true
        );

        $response = $this->postJson("/api/v1/playlists/{$playlist->id}/cover", [
            'cover' => $file,
        ]);

        $response->assertUnauthorized();
    }

    public function test_upload_cover_returns_403_for_other_users_playlist(): void
    {
        $otherUser = User::factory()->create();
        $playlist = Playlist::factory()->create([
            'user_id' => $otherUser->id,
        ]);

        $file = new \Illuminate\Http\UploadedFile(
            base_path('tests/fixtures/test-image.jpg'),
            'cover.jpg',
            'image/jpeg',
            null,
            true
        );

        $response = $this->actingAs($this->user)
            ->postJson("/api/v1/playlists/{$playlist->id}/cover", [
                'cover' => $file,
            ]);

        $response->assertForbidden();
    }

    public function test_upload_cover_validates_file_required(): void
    {
        $playlist = Playlist::factory()->create([
            'user_id' => $this->user->id,
        ]);

        $response = $this->actingAs($this->user)
            ->postJson("/api/v1/playlists/{$playlist->id}/cover", []);

        $response->assertUnprocessable()
            ->assertJsonValidationErrors(['cover']);
    }

    public function test_upload_cover_validates_file_is_image(): void
    {
        $playlist = Playlist::factory()->create([
            'user_id' => $this->user->id,
        ]);

        $file = \Illuminate\Http\UploadedFile::fake()->create('document.pdf', 100, 'application/pdf');

        $response = $this->actingAs($this->user)
            ->postJson("/api/v1/playlists/{$playlist->id}/cover", [
                'cover' => $file,
            ]);

        $response->assertUnprocessable()
            ->assertJsonValidationErrors(['cover']);
    }

    public function test_upload_cover_succeeds_for_regular_playlist(): void
    {
        $playlist = Playlist::factory()->create([
            'user_id' => $this->user->id,
            'is_smart' => false,
            'cover' => null,
        ]);

        $file = new \Illuminate\Http\UploadedFile(
            base_path('tests/fixtures/test-image.jpg'),
            'cover.jpg',
            'image/jpeg',
            null,
            true
        );

        $response = $this->actingAs($this->user)
            ->postJson("/api/v1/playlists/{$playlist->id}/cover", [
                'cover' => $file,
            ]);

        $response->assertOk()
            ->assertJsonStructure([
                'data' => ['id', 'name', 'cover_url'],
            ]);

        $playlist->refresh();
        $this->assertNotNull($playlist->cover);
    }

    public function test_upload_cover_succeeds_for_smart_playlist(): void
    {
        $playlist = Playlist::factory()->create([
            'user_id' => $this->user->id,
            'is_smart' => true,
            'cover' => null,
        ]);

        $file = new \Illuminate\Http\UploadedFile(
            base_path('tests/fixtures/test-image.jpg'),
            'cover.jpg',
            'image/jpeg',
            null,
            true
        );

        $response = $this->actingAs($this->user)
            ->postJson("/api/v1/playlists/{$playlist->id}/cover", [
                'cover' => $file,
            ]);

        $response->assertOk();

        $playlist->refresh();
        $this->assertNotNull($playlist->cover);
    }

    public function test_upload_cover_replaces_existing_cover(): void
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

        $response = $this->actingAs($this->user)
            ->postJson("/api/v1/playlists/{$playlist->id}/cover", [
                'cover' => $file,
            ]);

        $response->assertOk();

        $playlist->refresh();
        $this->assertNotEquals($oldCoverPath, $playlist->cover);
        Storage::disk('public')->assertMissing($oldCoverPath);
    }
}

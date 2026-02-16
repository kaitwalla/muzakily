<?php

declare(strict_types=1);

namespace Tests\Feature\Api\V1;

use App\Models\Album;
use App\Models\Artist;
use App\Models\DeletedItem;
use App\Models\Playlist;
use App\Models\Song;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Tests\TestCase;

class SyncEndpointTest extends TestCase
{
    use RefreshDatabase;

    private User $user;

    protected function setUp(): void
    {
        parent::setUp();
        $this->user = User::factory()->create();
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        parent::tearDown();
    }

    public function test_deleted_endpoint_requires_authentication(): void
    {
        $response = $this->getJson('/api/v1/deleted?since=2024-01-01T00:00:00Z');

        $response->assertUnauthorized();
    }

    public function test_deleted_endpoint_requires_since_parameter(): void
    {
        $response = $this->actingAs($this->user)->getJson('/api/v1/deleted');

        $response->assertUnprocessable()
            ->assertJsonValidationErrors(['since']);
    }

    public function test_deleted_endpoint_validates_since_is_date(): void
    {
        $response = $this->actingAs($this->user)->getJson('/api/v1/deleted?since=not-a-date');

        $response->assertUnprocessable()
            ->assertJsonValidationErrors(['since']);
    }

    public function test_deleted_endpoint_returns_songs_deleted_since(): void
    {
        // Create deleted items
        Carbon::setTestNow('2024-01-15 12:00:00');
        DeletedItem::recordDeletion('song', 'old-song-id');

        Carbon::setTestNow('2024-01-16 12:00:00');
        DeletedItem::recordDeletion('song', 'new-song-id');

        $response = $this->actingAs($this->user)
            ->getJson('/api/v1/deleted?since=2024-01-16T00:00:00Z');

        $response->assertOk()
            ->assertJsonPath('data.songs', ['new-song-id'])
            ->assertJsonPath('data.albums', [])
            ->assertJsonPath('data.artists', [])
            ->assertJsonPath('data.playlists', []);
    }

    public function test_deleted_endpoint_returns_albums_deleted_since(): void
    {
        Carbon::setTestNow('2024-01-16 12:00:00');
        DeletedItem::recordDeletion('album', 'album-uuid');

        $response = $this->actingAs($this->user)
            ->getJson('/api/v1/deleted?since=2024-01-15T00:00:00Z');

        $response->assertOk()
            ->assertJsonPath('data.albums', ['album-uuid']);
    }

    public function test_deleted_endpoint_returns_artists_deleted_since(): void
    {
        Carbon::setTestNow('2024-01-16 12:00:00');
        DeletedItem::recordDeletion('artist', 'artist-uuid');

        $response = $this->actingAs($this->user)
            ->getJson('/api/v1/deleted?since=2024-01-15T00:00:00Z');

        $response->assertOk()
            ->assertJsonPath('data.artists', ['artist-uuid']);
    }

    public function test_deleted_endpoint_returns_only_users_playlists(): void
    {
        $otherUser = User::factory()->create();

        Carbon::setTestNow('2024-01-16 12:00:00');
        DeletedItem::recordDeletion('playlist', 'my-playlist-id', $this->user->id);
        DeletedItem::recordDeletion('playlist', 'other-playlist-id', $otherUser->id);

        $response = $this->actingAs($this->user)
            ->getJson('/api/v1/deleted?since=2024-01-15T00:00:00Z');

        $response->assertOk()
            ->assertJsonPath('data.playlists', ['my-playlist-id']);
    }

    public function test_deleted_endpoint_returns_meta_timestamps(): void
    {
        $response = $this->actingAs($this->user)
            ->getJson('/api/v1/deleted?since=2024-01-15T00:00:00Z');

        $response->assertOk()
            ->assertJsonStructure([
                'data' => ['songs', 'albums', 'artists', 'playlists'],
                'meta' => ['since', 'queried_at'],
            ]);
    }

    public function test_sync_status_requires_authentication(): void
    {
        $response = $this->getJson('/api/v1/sync/status');

        $response->assertUnauthorized();
    }

    public function test_sync_status_returns_counts_and_timestamps(): void
    {
        // Create some data
        $artist = Artist::factory()->create();
        $album = Album::factory()->create(['artist_id' => $artist->id]);
        Song::factory()->count(3)->create(['artist_id' => $artist->id, 'album_id' => $album->id]);
        Playlist::factory()->count(2)->create(['user_id' => $this->user->id]);

        $response = $this->actingAs($this->user)->getJson('/api/v1/sync/status');

        $response->assertOk()
            ->assertJsonStructure([
                'data' => [
                    'songs' => ['count', 'last_updated'],
                    'albums' => ['count', 'last_updated'],
                    'artists' => ['count', 'last_updated'],
                    'playlists' => ['count', 'last_updated'],
                    'library_updated_at',
                ],
            ])
            ->assertJsonPath('data.songs.count', 3)
            ->assertJsonPath('data.albums.count', 1)
            ->assertJsonPath('data.artists.count', 1)
            ->assertJsonPath('data.playlists.count', 2);
    }

    public function test_sync_status_returns_only_users_playlist_count(): void
    {
        $otherUser = User::factory()->create();

        Playlist::factory()->count(3)->create(['user_id' => $this->user->id]);
        Playlist::factory()->count(5)->create(['user_id' => $otherUser->id]);

        $response = $this->actingAs($this->user)->getJson('/api/v1/sync/status');

        $response->assertOk()
            ->assertJsonPath('data.playlists.count', 3);
    }

    public function test_sync_status_returns_null_timestamps_when_no_data(): void
    {
        $response = $this->actingAs($this->user)->getJson('/api/v1/sync/status');

        $response->assertOk()
            ->assertJsonPath('data.songs.count', 0)
            ->assertJsonPath('data.songs.last_updated', null)
            ->assertJsonPath('data.library_updated_at', null);
    }

    public function test_sync_status_library_updated_at_is_most_recent(): void
    {
        Carbon::setTestNow('2024-01-10 12:00:00');
        $artist = Artist::factory()->create();

        Carbon::setTestNow('2024-01-15 12:00:00');
        $album = Album::factory()->create(['artist_id' => $artist->id]);

        Carbon::setTestNow('2024-01-20 12:00:00');
        Song::factory()->create(['artist_id' => $artist->id, 'album_id' => $album->id]);

        $response = $this->actingAs($this->user)->getJson('/api/v1/sync/status');

        $response->assertOk();

        $libraryUpdatedAt = $response->json('data.library_updated_at');
        $this->assertStringContainsString('2024-01-20', $libraryUpdatedAt);
    }
}

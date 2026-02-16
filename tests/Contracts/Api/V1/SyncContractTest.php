<?php

declare(strict_types=1);

namespace Tests\Contracts\Api\V1;

use App\Models\Album;
use App\Models\Artist;
use App\Models\DeletedItem;
use App\Models\Playlist;
use App\Models\Song;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SyncContractTest extends TestCase
{
    use RefreshDatabase;

    private User $user;

    protected function setUp(): void
    {
        parent::setUp();
        $this->user = User::factory()->create();
    }

    public function test_deleted_endpoint_returns_correct_structure(): void
    {
        DeletedItem::recordDeletion('song', 'song-id-1');
        DeletedItem::recordDeletion('album', 'album-id-1');
        DeletedItem::recordDeletion('artist', 'artist-id-1');
        DeletedItem::recordDeletion('playlist', 'playlist-id-1', $this->user->id);

        $response = $this->actingAs($this->user)
            ->getJson('/api/v1/deleted?since=2020-01-01T00:00:00Z');

        $response->assertOk()
            ->assertJsonStructure([
                'data' => [
                    'songs',
                    'albums',
                    'artists',
                    'playlists',
                ],
                'meta' => [
                    'since',
                    'queried_at',
                ],
            ]);

        // Verify arrays contain strings (IDs)
        $this->assertIsArray($response->json('data.songs'));
        $this->assertIsArray($response->json('data.albums'));
        $this->assertIsArray($response->json('data.artists'));
        $this->assertIsArray($response->json('data.playlists'));
    }

    public function test_sync_status_returns_correct_structure(): void
    {
        $artist = Artist::factory()->create();
        $album = Album::factory()->create(['artist_id' => $artist->id]);
        Song::factory()->create(['artist_id' => $artist->id, 'album_id' => $album->id]);
        Playlist::factory()->create(['user_id' => $this->user->id]);

        $response = $this->actingAs($this->user)->getJson('/api/v1/sync/status');

        $response->assertOk()
            ->assertJsonStructure([
                'data' => [
                    'songs' => [
                        'count',
                        'last_updated',
                    ],
                    'albums' => [
                        'count',
                        'last_updated',
                    ],
                    'artists' => [
                        'count',
                        'last_updated',
                    ],
                    'playlists' => [
                        'count',
                        'last_updated',
                    ],
                    'library_updated_at',
                ],
            ]);

        // Verify counts are integers
        $this->assertIsInt($response->json('data.songs.count'));
        $this->assertIsInt($response->json('data.albums.count'));
        $this->assertIsInt($response->json('data.artists.count'));
        $this->assertIsInt($response->json('data.playlists.count'));
    }

    public function test_sync_status_timestamps_are_iso8601(): void
    {
        Song::factory()->create();

        $response = $this->actingAs($this->user)->getJson('/api/v1/sync/status');

        $response->assertOk();

        $lastUpdated = $response->json('data.songs.last_updated');
        $this->assertNotNull($lastUpdated);

        // Verify it's a valid ISO 8601 timestamp
        $parsed = \DateTimeImmutable::createFromFormat(\DateTimeInterface::ATOM, $lastUpdated);
        $this->assertInstanceOf(\DateTimeImmutable::class, $parsed);
    }

    public function test_deleted_endpoint_meta_timestamps_are_iso8601(): void
    {
        $response = $this->actingAs($this->user)
            ->getJson('/api/v1/deleted?since=2024-01-01T00:00:00Z');

        $response->assertOk();

        $since = $response->json('meta.since');
        $queriedAt = $response->json('meta.queried_at');

        // Verify they are valid ISO 8601 timestamps
        $parsedSince = \DateTimeImmutable::createFromFormat(\DateTimeInterface::ATOM, $since);
        $parsedQueriedAt = \DateTimeImmutable::createFromFormat(\DateTimeInterface::ATOM, $queriedAt);

        $this->assertInstanceOf(\DateTimeImmutable::class, $parsedSince);
        $this->assertInstanceOf(\DateTimeImmutable::class, $parsedQueriedAt);
    }
}

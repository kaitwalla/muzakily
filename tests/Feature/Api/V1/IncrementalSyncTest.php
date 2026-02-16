<?php

declare(strict_types=1);

namespace Tests\Feature\Api\V1;

use App\Models\Album;
use App\Models\Artist;
use App\Models\Playlist;
use App\Models\Song;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Tests\TestCase;

class IncrementalSyncTest extends TestCase
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

    public function test_songs_index_filters_by_updated_since(): void
    {
        Carbon::setTestNow('2024-01-10 12:00:00');
        Song::factory()->create(['title' => 'Old Song']);

        Carbon::setTestNow('2024-01-20 12:00:00');
        Song::factory()->create(['title' => 'New Song']);

        $response = $this->actingAs($this->user)
            ->getJson('/api/v1/songs?updated_since=2024-01-15T00:00:00Z');

        $response->assertOk();
        $this->assertCount(1, $response->json('data'));
        $this->assertEquals('New Song', $response->json('data.0.title'));
    }

    public function test_songs_updated_since_includes_exact_timestamp(): void
    {
        Carbon::setTestNow('2024-01-15 12:00:00');
        Song::factory()->create(['title' => 'Exact Match']);

        $response = $this->actingAs($this->user)
            ->getJson('/api/v1/songs?updated_since=2024-01-15T12:00:00Z');

        $response->assertOk();
        $this->assertCount(1, $response->json('data'));
    }

    public function test_albums_index_filters_by_updated_since(): void
    {
        $artist = Artist::factory()->create();

        Carbon::setTestNow('2024-01-10 12:00:00');
        Album::factory()->create(['name' => 'Old Album', 'artist_id' => $artist->id]);

        Carbon::setTestNow('2024-01-20 12:00:00');
        Album::factory()->create(['name' => 'New Album', 'artist_id' => $artist->id]);

        $response = $this->actingAs($this->user)
            ->getJson('/api/v1/albums?updated_since=2024-01-15T00:00:00Z');

        $response->assertOk();
        $this->assertCount(1, $response->json('data'));
        $this->assertEquals('New Album', $response->json('data.0.name'));
    }

    public function test_artists_index_filters_by_updated_since(): void
    {
        Carbon::setTestNow('2024-01-10 12:00:00');
        Artist::factory()->create(['name' => 'Old Artist']);

        Carbon::setTestNow('2024-01-20 12:00:00');
        Artist::factory()->create(['name' => 'New Artist']);

        $response = $this->actingAs($this->user)
            ->getJson('/api/v1/artists?updated_since=2024-01-15T00:00:00Z');

        $response->assertOk();
        $this->assertCount(1, $response->json('data'));
        $this->assertEquals('New Artist', $response->json('data.0.name'));
    }

    public function test_playlists_index_filters_by_updated_since(): void
    {
        Carbon::setTestNow('2024-01-10 12:00:00');
        Playlist::factory()->create(['name' => 'Old Playlist', 'user_id' => $this->user->id]);

        Carbon::setTestNow('2024-01-20 12:00:00');
        Playlist::factory()->create(['name' => 'New Playlist', 'user_id' => $this->user->id]);

        $response = $this->actingAs($this->user)
            ->getJson('/api/v1/playlists?updated_since=2024-01-15T00:00:00Z');

        $response->assertOk();
        $this->assertCount(1, $response->json('data'));
        $this->assertEquals('New Playlist', $response->json('data.0.name'));
    }

    public function test_updated_since_works_with_other_filters(): void
    {
        $artist = Artist::factory()->create();

        Carbon::setTestNow('2024-01-20 12:00:00');
        Song::factory()->create([
            'title' => 'Matching Song',
            'artist_id' => $artist->id,
        ]);
        Song::factory()->create([
            'title' => 'Other Song',
            'artist_id' => null,
        ]);

        $response = $this->actingAs($this->user)
            ->getJson("/api/v1/songs?updated_since=2024-01-15T00:00:00Z&artist_id={$artist->uuid}");

        $response->assertOk();
        $this->assertCount(1, $response->json('data'));
        $this->assertEquals('Matching Song', $response->json('data.0.title'));
    }

    public function test_updated_since_with_iso8601_format(): void
    {
        Carbon::setTestNow('2024-01-20 15:30:00');
        Song::factory()->create(['title' => 'Test Song']);

        // Use URL-encoded + sign or Z suffix for ISO 8601 format
        $response = $this->actingAs($this->user)
            ->getJson('/api/v1/songs?updated_since=2024-01-20T15:30:00Z');

        $response->assertOk();
        $this->assertCount(1, $response->json('data'));
    }

    public function test_updated_since_returns_empty_when_no_updates(): void
    {
        Carbon::setTestNow('2024-01-10 12:00:00');
        Song::factory()->count(5)->create();

        $response = $this->actingAs($this->user)
            ->getJson('/api/v1/songs?updated_since=2024-01-15T00:00:00Z');

        $response->assertOk();
        $this->assertCount(0, $response->json('data'));
    }

    public function test_songs_without_updated_since_returns_all(): void
    {
        Carbon::setTestNow('2024-01-10 12:00:00');
        Song::factory()->count(2)->create();

        Carbon::setTestNow('2024-01-20 12:00:00');
        Song::factory()->count(3)->create();

        $response = $this->actingAs($this->user)->getJson('/api/v1/songs');

        $response->assertOk();
        $this->assertCount(5, $response->json('data'));
    }

    public function test_updated_since_with_invalid_date_returns_422(): void
    {
        $response = $this->actingAs($this->user)
            ->getJson('/api/v1/songs?updated_since=not-a-valid-date');

        $response->assertStatus(422);
    }

    public function test_albums_updated_since_with_invalid_date_returns_422(): void
    {
        $response = $this->actingAs($this->user)
            ->getJson('/api/v1/albums?updated_since=invalid');

        $response->assertStatus(422);
    }

    public function test_artists_updated_since_with_invalid_date_returns_422(): void
    {
        $response = $this->actingAs($this->user)
            ->getJson('/api/v1/artists?updated_since=invalid');

        $response->assertStatus(422);
    }

    public function test_playlists_updated_since_with_invalid_date_returns_422(): void
    {
        $response = $this->actingAs($this->user)
            ->getJson('/api/v1/playlists?updated_since=invalid');

        $response->assertStatus(422);
    }
}

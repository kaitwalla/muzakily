<?php

declare(strict_types=1);

namespace Tests\Feature\Api\V1;

use App\Jobs\RefreshSmartPlaylistJob;
use App\Models\Playlist;
use App\Models\Song;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use Tests\TestCase;

class PlaylistSongsEndpointTest extends TestCase
{
    use RefreshDatabase;

    private User $user;

    protected function setUp(): void
    {
        parent::setUp();
        $this->user = User::factory()->create();
    }

    public function test_songs_endpoint_returns_paginated_results(): void
    {
        $playlist = Playlist::factory()->create(['user_id' => $this->user->id]);
        $songs = Song::factory()->count(100)->create();

        foreach ($songs as $index => $song) {
            $playlist->songs()->attach($song->id, ['position' => $index]);
        }

        $response = $this->actingAs($this->user)->getJson("/api/v1/playlists/{$playlist->id}/songs");

        $response->assertOk()
            ->assertJsonCount(75, 'data')
            ->assertJsonPath('meta.total', 100)
            ->assertJsonPath('meta.limit', 75)
            ->assertJsonPath('meta.offset', 0)
            ->assertJsonPath('meta.has_more', true);
    }

    public function test_songs_endpoint_respects_custom_limit(): void
    {
        $playlist = Playlist::factory()->create(['user_id' => $this->user->id]);
        $songs = Song::factory()->count(50)->create();

        foreach ($songs as $index => $song) {
            $playlist->songs()->attach($song->id, ['position' => $index]);
        }

        $response = $this->actingAs($this->user)->getJson("/api/v1/playlists/{$playlist->id}/songs?limit=20");

        $response->assertOk()
            ->assertJsonCount(20, 'data')
            ->assertJsonPath('meta.limit', 20)
            ->assertJsonPath('meta.has_more', true);
    }

    public function test_songs_endpoint_respects_offset(): void
    {
        $playlist = Playlist::factory()->create(['user_id' => $this->user->id]);
        $songs = Song::factory()->count(30)->create();

        foreach ($songs as $index => $song) {
            $playlist->songs()->attach($song->id, ['position' => $index]);
        }

        $response = $this->actingAs($this->user)->getJson("/api/v1/playlists/{$playlist->id}/songs?limit=10&offset=20");

        $response->assertOk()
            ->assertJsonCount(10, 'data')
            ->assertJsonPath('meta.offset', 20)
            ->assertJsonPath('meta.has_more', false);
    }

    public function test_songs_endpoint_enforces_max_limit(): void
    {
        $playlist = Playlist::factory()->create(['user_id' => $this->user->id]);
        Song::factory()->count(5)->create()->each(function ($song, $index) use ($playlist) {
            $playlist->songs()->attach($song->id, ['position' => $index]);
        });

        $response = $this->actingAs($this->user)->getJson("/api/v1/playlists/{$playlist->id}/songs?limit=1000");

        $response->assertOk()
            ->assertJsonPath('meta.limit', 500);
    }

    public function test_songs_endpoint_handles_negative_offset(): void
    {
        $playlist = Playlist::factory()->create(['user_id' => $this->user->id]);
        Song::factory()->count(5)->create()->each(function ($song, $index) use ($playlist) {
            $playlist->songs()->attach($song->id, ['position' => $index]);
        });

        $response = $this->actingAs($this->user)->getJson("/api/v1/playlists/{$playlist->id}/songs?offset=-10");

        $response->assertOk()
            ->assertJsonPath('meta.offset', 0);
    }

    public function test_songs_endpoint_works_for_smart_playlist_materialized(): void
    {
        // Create songs first
        $songs = Song::factory()->count(100)->create(['title' => 'Materialized Test Song']);

        // Create playlist without triggering observer job
        $playlist = Playlist::factory()->create([
            'user_id' => $this->user->id,
            'is_smart' => true,
            'rules' => [['logic' => 'and', 'rules' => [['field' => 'title', 'operator' => 'contains', 'value' => 'Materialized Test']]]],
            'materialized_at' => now(),
        ]);

        // Manually clear any songs added by observer and add our own
        $playlist->songs()->detach();
        foreach ($songs as $index => $song) {
            $playlist->songs()->attach($song->id, ['position' => $index]);
        }

        $response = $this->actingAs($this->user)->getJson("/api/v1/playlists/{$playlist->id}/songs?limit=25");

        $response->assertOk()
            ->assertJsonCount(25, 'data')
            ->assertJsonPath('meta.total', 100)
            ->assertJsonPath('meta.has_more', true);
    }

    public function test_songs_endpoint_works_for_smart_playlist_dynamic(): void
    {
        // Use a unique identifier for this test's songs
        $uniqueMarker = 'DynamicTestUnique' . uniqid();

        Song::factory()->count(50)->create(['title' => $uniqueMarker . ' Yes']);
        Song::factory()->count(30)->create(['title' => 'NoMatchForDynamic']);

        // Create playlist - it will be materialized by observer, but we'll force dynamic evaluation
        $playlist = Playlist::factory()->create([
            'user_id' => $this->user->id,
            'is_smart' => true,
            'rules' => [['logic' => 'and', 'rules' => [['field' => 'title', 'operator' => 'contains', 'value' => $uniqueMarker]]]],
        ]);

        // Force dynamic evaluation by setting materialized_at to null
        $playlist->update(['materialized_at' => null]);

        $response = $this->actingAs($this->user)->getJson("/api/v1/playlists/{$playlist->id}/songs?limit=20");

        $response->assertOk()
            ->assertJsonCount(20, 'data')
            ->assertJsonPath('meta.total', 50)
            ->assertJsonPath('meta.has_more', true);
    }

    public function test_songs_endpoint_requires_authentication(): void
    {
        $playlist = Playlist::factory()->create(['user_id' => $this->user->id]);

        $response = $this->getJson("/api/v1/playlists/{$playlist->id}/songs");

        $response->assertUnauthorized();
    }

    public function test_songs_endpoint_returns_403_for_other_users_playlist(): void
    {
        $otherUser = User::factory()->create();
        $playlist = Playlist::factory()->create(['user_id' => $otherUser->id]);

        $response = $this->actingAs($this->user)->getJson("/api/v1/playlists/{$playlist->id}/songs");

        $response->assertForbidden();
    }

    public function test_refresh_endpoint_queues_job_for_smart_playlist(): void
    {
        Queue::fake();

        $playlist = Playlist::factory()->create([
            'user_id' => $this->user->id,
            'is_smart' => true,
            'rules' => [['logic' => 'and', 'rules' => [['field' => 'title', 'operator' => 'contains', 'value' => 'Test']]]],
        ]);

        $response = $this->actingAs($this->user)->postJson("/api/v1/playlists/{$playlist->id}/refresh");

        $response->assertOk()
            ->assertJsonPath('message', 'Playlist refresh has been queued');

        Queue::assertPushed(RefreshSmartPlaylistJob::class, function ($job) use ($playlist) {
            return $job->playlist->id === $playlist->id;
        });
    }

    public function test_refresh_endpoint_returns_422_for_regular_playlist(): void
    {
        $playlist = Playlist::factory()->create([
            'user_id' => $this->user->id,
            'is_smart' => false,
        ]);

        $response = $this->actingAs($this->user)->postJson("/api/v1/playlists/{$playlist->id}/refresh");

        $response->assertUnprocessable()
            ->assertJsonPath('error.code', 'INVALID_OPERATION');
    }

    public function test_refresh_endpoint_requires_authentication(): void
    {
        $playlist = Playlist::factory()->create([
            'user_id' => $this->user->id,
            'is_smart' => true,
        ]);

        $response = $this->postJson("/api/v1/playlists/{$playlist->id}/refresh");

        $response->assertUnauthorized();
    }

    public function test_refresh_endpoint_returns_403_for_other_users_playlist(): void
    {
        $otherUser = User::factory()->create();
        $playlist = Playlist::factory()->create([
            'user_id' => $otherUser->id,
            'is_smart' => true,
        ]);

        $response = $this->actingAs($this->user)->postJson("/api/v1/playlists/{$playlist->id}/refresh");

        $response->assertForbidden();
    }
}

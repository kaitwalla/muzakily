<?php

declare(strict_types=1);

namespace Tests\Feature\Api\V1;

use App\Models\Playlist;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PlaylistReorderEndpointTest extends TestCase
{
    use RefreshDatabase;

    private User $user;

    protected function setUp(): void
    {
        parent::setUp();
        $this->user = User::factory()->create();
    }

    public function test_reorder_endpoint_updates_playlist_positions(): void
    {
        $playlist1 = Playlist::factory()->create(['user_id' => $this->user->id, 'position' => 0]);
        $playlist2 = Playlist::factory()->create(['user_id' => $this->user->id, 'position' => 1]);
        $playlist3 = Playlist::factory()->create(['user_id' => $this->user->id, 'position' => 2]);

        $response = $this->actingAs($this->user)->putJson('/api/v1/playlists/reorder', [
            'playlist_ids' => [$playlist3->id, $playlist1->id, $playlist2->id],
        ]);

        $response->assertOk();

        $this->assertEquals(0, $playlist3->fresh()->position);
        $this->assertEquals(1, $playlist1->fresh()->position);
        $this->assertEquals(2, $playlist2->fresh()->position);
    }

    public function test_reorder_endpoint_requires_authentication(): void
    {
        $playlist = Playlist::factory()->create(['user_id' => $this->user->id]);

        $response = $this->putJson('/api/v1/playlists/reorder', [
            'playlist_ids' => [$playlist->id],
        ]);

        $response->assertUnauthorized();
    }

    public function test_reorder_endpoint_validates_playlist_ids_are_required(): void
    {
        $response = $this->actingAs($this->user)->putJson('/api/v1/playlists/reorder', []);

        $response->assertUnprocessable()
            ->assertJsonValidationErrors(['playlist_ids']);
    }

    public function test_reorder_endpoint_validates_playlist_ids_is_array(): void
    {
        $response = $this->actingAs($this->user)->putJson('/api/v1/playlists/reorder', [
            'playlist_ids' => 'not-an-array',
        ]);

        $response->assertUnprocessable()
            ->assertJsonValidationErrors(['playlist_ids']);
    }

    public function test_reorder_endpoint_validates_playlist_ids_are_uuids(): void
    {
        $response = $this->actingAs($this->user)->putJson('/api/v1/playlists/reorder', [
            'playlist_ids' => ['not-a-uuid', 'also-not-a-uuid'],
        ]);

        $response->assertUnprocessable()
            ->assertJsonValidationErrors(['playlist_ids.0', 'playlist_ids.1']);
    }

    public function test_reorder_endpoint_ignores_other_users_playlists(): void
    {
        $otherUser = User::factory()->create();
        $myPlaylist = Playlist::factory()->create(['user_id' => $this->user->id, 'position' => 0]);
        $otherPlaylist = Playlist::factory()->create(['user_id' => $otherUser->id, 'position' => 0]);

        $response = $this->actingAs($this->user)->putJson('/api/v1/playlists/reorder', [
            'playlist_ids' => [$otherPlaylist->id, $myPlaylist->id],
        ]);

        $response->assertOk();

        // My playlist should be at position 0
        $this->assertEquals(0, $myPlaylist->fresh()->position);
        // Other user's playlist should be unchanged
        $this->assertEquals(0, $otherPlaylist->fresh()->position);
    }

    public function test_index_endpoint_returns_playlists_ordered_by_position(): void
    {
        $playlist1 = Playlist::factory()->create(['user_id' => $this->user->id, 'name' => 'Zebra', 'position' => 2]);
        $playlist2 = Playlist::factory()->create(['user_id' => $this->user->id, 'name' => 'Alpha', 'position' => 0]);
        $playlist3 = Playlist::factory()->create(['user_id' => $this->user->id, 'name' => 'Beta', 'position' => 1]);

        $response = $this->actingAs($this->user)->getJson('/api/v1/playlists');

        $response->assertOk();

        $responseIds = collect($response->json('data'))->pluck('id')->toArray();
        $this->assertEquals([$playlist2->id, $playlist3->id, $playlist1->id], $responseIds);
    }

    public function test_new_playlists_get_highest_position(): void
    {
        $existingPlaylist = Playlist::factory()->create(['user_id' => $this->user->id, 'position' => 5]);

        $response = $this->actingAs($this->user)->postJson('/api/v1/playlists', [
            'name' => 'New Playlist',
        ]);

        $response->assertCreated();

        $newPlaylist = Playlist::where('name', 'New Playlist')->first();
        $this->assertEquals(6, $newPlaylist->position);
    }

    public function test_first_playlist_gets_position_zero(): void
    {
        $response = $this->actingAs($this->user)->postJson('/api/v1/playlists', [
            'name' => 'First Playlist',
        ]);

        $response->assertCreated();

        $playlist = Playlist::where('name', 'First Playlist')->first();
        $this->assertEquals(0, $playlist->position);
    }
}

<?php

declare(strict_types=1);

namespace Tests\Feature\Api\V1;

use App\Models\DeletedItem;
use App\Models\Playlist;
use App\Models\Song;
use App\Models\Tag;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class SongDeleteTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;
    private User $user;

    protected function setUp(): void
    {
        parent::setUp();
        $this->admin = User::factory()->admin()->create();
        $this->user = User::factory()->create(['role' => 'user']);
        Storage::fake('r2');
    }

    // ==================== Single Delete Tests ====================

    public function test_admin_can_delete_song(): void
    {
        $song = Song::factory()->create();

        $response = $this->actingAs($this->admin)->deleteJson("/api/v1/songs/{$song->id}");

        $response->assertNoContent();
        $this->assertSoftDeleted('songs', ['id' => $song->id]);
    }

    public function test_non_admin_cannot_delete_song(): void
    {
        $song = Song::factory()->create();

        $response = $this->actingAs($this->user)->deleteJson("/api/v1/songs/{$song->id}");

        $response->assertForbidden();
        $this->assertDatabaseHas('songs', ['id' => $song->id, 'deleted_at' => null]);
    }

    public function test_unauthenticated_user_cannot_delete_song(): void
    {
        $song = Song::factory()->create();

        $response = $this->deleteJson("/api/v1/songs/{$song->id}");

        $response->assertUnauthorized();
    }

    public function test_delete_nonexistent_song_returns_404(): void
    {
        $fakeId = '00000000-0000-0000-0000-000000000000';

        $response = $this->actingAs($this->admin)->deleteJson("/api/v1/songs/{$fakeId}");

        $response->assertNotFound();
    }

    public function test_delete_records_deletion_for_sync(): void
    {
        $song = Song::factory()->create();
        $songId = $song->id;

        $this->actingAs($this->admin)->deleteJson("/api/v1/songs/{$songId}");

        $this->assertDatabaseHas('deleted_items', [
            'deletable_type' => 'song',
            'deletable_id' => $songId,
        ]);
    }

    public function test_delete_preserves_playlist_pivot_records(): void
    {
        $song = Song::factory()->create();
        $playlist = Playlist::factory()->create(['user_id' => $this->admin->id]);
        $playlist->songs()->attach($song->id, ['position' => 1]);

        $this->actingAs($this->admin)->deleteJson("/api/v1/songs/{$song->id}");

        $this->assertSoftDeleted('songs', ['id' => $song->id]);
        // Pivot record remains after soft delete (no cascade)
        $this->assertDatabaseHas('playlist_song', [
            'song_id' => $song->id,
            'playlist_id' => $playlist->id,
        ]);
    }

    public function test_delete_preserves_tag_pivot_records(): void
    {
        $song = Song::factory()->create();
        $tag = Tag::factory()->create();
        $song->tags()->attach($tag->id);

        $this->actingAs($this->admin)->deleteJson("/api/v1/songs/{$song->id}");

        $this->assertSoftDeleted('songs', ['id' => $song->id]);
        // Pivot record remains after soft delete (no cascade)
        $this->assertDatabaseHas('song_tag', [
            'song_id' => $song->id,
            'tag_id' => $tag->id,
        ]);
    }

    // ==================== Bulk Delete Tests ====================

    public function test_admin_can_bulk_delete_songs(): void
    {
        $songs = Song::factory()->count(3)->create();
        $songIds = $songs->pluck('id')->toArray();

        $response = $this->actingAs($this->admin)->deleteJson('/api/v1/songs/bulk', [
            'song_ids' => $songIds,
        ]);

        $response->assertNoContent();

        foreach ($songIds as $songId) {
            $this->assertSoftDeleted('songs', ['id' => $songId]);
        }
    }

    public function test_non_admin_cannot_bulk_delete_songs(): void
    {
        $songs = Song::factory()->count(2)->create();
        $songIds = $songs->pluck('id')->toArray();

        $response = $this->actingAs($this->user)->deleteJson('/api/v1/songs/bulk', [
            'song_ids' => $songIds,
        ]);

        $response->assertForbidden();

        foreach ($songIds as $songId) {
            $this->assertDatabaseHas('songs', ['id' => $songId, 'deleted_at' => null]);
        }
    }

    public function test_unauthenticated_user_cannot_bulk_delete(): void
    {
        $songs = Song::factory()->count(2)->create();

        $response = $this->deleteJson('/api/v1/songs/bulk', [
            'song_ids' => $songs->pluck('id')->toArray(),
        ]);

        $response->assertUnauthorized();
    }

    public function test_bulk_delete_requires_song_ids(): void
    {
        $response = $this->actingAs($this->admin)->deleteJson('/api/v1/songs/bulk', []);

        $response->assertUnprocessable()
            ->assertJsonValidationErrors(['song_ids']);
    }

    public function test_bulk_delete_requires_valid_uuids(): void
    {
        $response = $this->actingAs($this->admin)->deleteJson('/api/v1/songs/bulk', [
            'song_ids' => ['not-a-uuid', 'also-not-valid'],
        ]);

        $response->assertUnprocessable()
            ->assertJsonValidationErrors(['song_ids.0', 'song_ids.1']);
    }

    public function test_bulk_delete_requires_existing_songs(): void
    {
        $fakeId = '00000000-0000-0000-0000-000000000000';

        $response = $this->actingAs($this->admin)->deleteJson('/api/v1/songs/bulk', [
            'song_ids' => [$fakeId],
        ]);

        $response->assertUnprocessable()
            ->assertJsonValidationErrors(['song_ids.0']);
    }

    public function test_bulk_delete_records_deletions_for_sync(): void
    {
        $songs = Song::factory()->count(2)->create();
        $songIds = $songs->pluck('id')->toArray();

        $this->actingAs($this->admin)->deleteJson('/api/v1/songs/bulk', [
            'song_ids' => $songIds,
        ]);

        foreach ($songIds as $songId) {
            $this->assertDatabaseHas('deleted_items', [
                'deletable_type' => 'song',
                'deletable_id' => $songId,
            ]);
        }
    }

    public function test_bulk_delete_handles_large_batch(): void
    {
        $songs = Song::factory()->count(50)->create();
        $songIds = $songs->pluck('id')->toArray();

        $response = $this->actingAs($this->admin)->deleteJson('/api/v1/songs/bulk', [
            'song_ids' => $songIds,
        ]);

        $response->assertNoContent();

        $this->assertEquals(50, Song::onlyTrashed()->count());
    }

    public function test_bulk_delete_is_idempotent_for_already_deleted(): void
    {
        $song = Song::factory()->create();
        $song->delete(); // Already soft deleted

        $existingSong = Song::factory()->create();

        // Attempt to delete both - should succeed (idempotent behavior)
        // The exists rule doesn't exclude soft-deleted records since they're still in the DB
        $response = $this->actingAs($this->admin)->deleteJson('/api/v1/songs/bulk', [
            'song_ids' => [$song->id, $existingSong->id],
        ]);

        $response->assertNoContent();

        // Both should be soft deleted
        $this->assertSoftDeleted('songs', ['id' => $song->id]);
        $this->assertSoftDeleted('songs', ['id' => $existingSong->id]);
    }
}

<?php

declare(strict_types=1);

namespace Tests\Feature\Api\V1;

use App\Models\Song;
use App\Models\Tag;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class TagEndpointTest extends TestCase
{
    use RefreshDatabase;

    private User $user;
    private User $admin;

    protected function setUp(): void
    {
        parent::setUp();
        $this->user = User::factory()->create(['role' => 'user']);
        $this->admin = User::factory()->create(['role' => 'admin']);
    }

    public function test_list_tags_returns_all_tags(): void
    {
        Tag::factory()->count(3)->create();

        $response = $this->actingAs($this->user)->getJson('/api/v1/tags');

        $response->assertOk()
            ->assertJsonCount(3, 'data');
    }

    public function test_list_tags_includes_hierarchy(): void
    {
        $parent = Tag::factory()->create(['name' => 'Xmas']);
        $child = Tag::factory()->create(['name' => 'Xmas/Contemporary', 'parent_id' => $parent->id]);

        $response = $this->actingAs($this->user)->getJson('/api/v1/tags');

        $response->assertOk()
            ->assertJsonPath('data.0.children.0.id', $child->id);
    }

    public function test_list_tags_flat_mode(): void
    {
        $parent = Tag::factory()->create(['name' => 'Xmas']);
        Tag::factory()->create(['name' => 'Xmas/Contemporary', 'parent_id' => $parent->id]);

        $response = $this->actingAs($this->user)->getJson('/api/v1/tags?flat=true');

        $response->assertOk()
            ->assertJsonCount(2, 'data');
    }

    public function test_list_tags_includes_song_counts(): void
    {
        $tag = Tag::factory()->create();
        $songs = Song::factory()->count(5)->create();
        $tag->songs()->attach($songs->pluck('id'));
        $tag->updateSongCount();

        $response = $this->actingAs($this->user)->getJson('/api/v1/tags');

        $response->assertOk()
            ->assertJsonPath('data.0.song_count', 5);
    }

    public function test_show_tag_returns_tag_details(): void
    {
        $tag = Tag::factory()->create(['name' => 'Rock', 'color' => '#e74c3c']);

        $response = $this->actingAs($this->user)->getJson("/api/v1/tags/{$tag->id}");

        $response->assertOk()
            ->assertJsonPath('data.name', 'Rock')
            ->assertJsonPath('data.color', '#e74c3c');
    }

    public function test_show_tag_returns_404_for_nonexistent(): void
    {
        $response = $this->actingAs($this->user)->getJson('/api/v1/tags/99999');

        $response->assertNotFound();
    }

    public function test_user_can_create_tag(): void
    {
        $response = $this->actingAs($this->user)->postJson('/api/v1/tags', [
            'name' => 'New Tag',
            'color' => '#3498db',
        ]);

        $response->assertCreated()
            ->assertJsonPath('data.name', 'New Tag')
            ->assertJsonPath('data.color', '#3498db');

        $this->assertDatabaseHas('tags', ['name' => 'New Tag']);
    }

    public function test_admin_can_create_tag(): void
    {
        $response = $this->actingAs($this->admin)->postJson('/api/v1/tags', [
            'name' => 'Admin Tag',
            'color' => '#3498db',
        ]);

        $response->assertCreated()
            ->assertJsonPath('data.name', 'Admin Tag')
            ->assertJsonPath('data.color', '#3498db');

        $this->assertDatabaseHas('tags', ['name' => 'Admin Tag']);
    }

    public function test_create_tag_generates_slug(): void
    {
        $response = $this->actingAs($this->admin)->postJson('/api/v1/tags', [
            'name' => 'Rock & Roll',
        ]);

        $response->assertCreated()
            ->assertJsonPath('data.slug', 'rock-roll');
    }

    public function test_create_tag_with_parent(): void
    {
        $parent = Tag::factory()->create(['name' => 'Xmas']);

        $response = $this->actingAs($this->admin)->postJson('/api/v1/tags', [
            'name' => 'Contemporary',
            'parent_id' => $parent->id,
        ]);

        $response->assertCreated()
            ->assertJsonPath('data.parent_id', $parent->id);
    }

    public function test_create_tag_validates_name_required(): void
    {
        $response = $this->actingAs($this->admin)->postJson('/api/v1/tags', []);

        $response->assertUnprocessable()
            ->assertJsonValidationErrors(['name']);
    }

    public function test_user_can_update_tag(): void
    {
        $tag = Tag::factory()->create(['name' => 'Old Name']);

        $response = $this->actingAs($this->user)->putJson("/api/v1/tags/{$tag->id}", [
            'name' => 'New Name',
            'color' => '#e74c3c',
        ]);

        $response->assertOk()
            ->assertJsonPath('data.name', 'New Name')
            ->assertJsonPath('data.color', '#e74c3c');
    }

    public function test_admin_can_update_tag(): void
    {
        $tag = Tag::factory()->create(['name' => 'Old Name']);

        $response = $this->actingAs($this->admin)->putJson("/api/v1/tags/{$tag->id}", [
            'name' => 'Admin Updated',
            'color' => '#e74c3c',
        ]);

        $response->assertOk()
            ->assertJsonPath('data.name', 'Admin Updated')
            ->assertJsonPath('data.color', '#e74c3c');
    }

    public function test_user_can_delete_tag(): void
    {
        $tag = Tag::factory()->create();

        $response = $this->actingAs($this->user)->deleteJson("/api/v1/tags/{$tag->id}");

        $response->assertNoContent();
        $this->assertDatabaseMissing('tags', ['id' => $tag->id]);
    }

    public function test_admin_can_delete_tag(): void
    {
        $tag = Tag::factory()->create();

        $response = $this->actingAs($this->admin)->deleteJson("/api/v1/tags/{$tag->id}");

        $response->assertNoContent();
        $this->assertDatabaseMissing('tags', ['id' => $tag->id]);
    }

    public function test_list_tag_songs(): void
    {
        $tag = Tag::factory()->create();
        $songs = Song::factory()->count(3)->create();
        $tag->songs()->attach($songs->pluck('id'));

        $response = $this->actingAs($this->user)->getJson("/api/v1/tags/{$tag->id}/songs");

        $response->assertOk()
            ->assertJsonCount(3, 'data');
    }

    public function test_list_tag_songs_includes_children_when_requested(): void
    {
        $parent = Tag::factory()->create();
        $child = Tag::factory()->create(['parent_id' => $parent->id]);
        $parentSong = Song::factory()->create();
        $childSong = Song::factory()->create();
        $parent->songs()->attach($parentSong);
        $child->songs()->attach($childSong);

        $response = $this->actingAs($this->user)
            ->getJson("/api/v1/tags/{$parent->id}/songs?include_children=true");

        $response->assertOk()
            ->assertJsonCount(2, 'data');
    }

    public function test_add_tags_to_song(): void
    {
        // All authenticated users can tag songs
        $song = Song::factory()->create();
        $tags = Tag::factory()->count(2)->create();

        $response = $this->actingAs($this->user)->postJson("/api/v1/songs/{$song->id}/tags", [
            'tag_ids' => $tags->pluck('id')->toArray(),
        ]);

        $response->assertOk()
            ->assertJsonCount(2, 'data.tags');
    }

    public function test_add_tags_requires_at_least_one_tag(): void
    {
        $song = Song::factory()->create();

        $response = $this->actingAs($this->user)->postJson("/api/v1/songs/{$song->id}/tags", [
            'tag_ids' => [],
        ]);

        $response->assertUnprocessable()
            ->assertJsonValidationErrors(['tag_ids']);
    }

    public function test_remove_tags_from_song(): void
    {
        $song = Song::factory()->create();
        $tag1 = Tag::factory()->create();
        $tag2 = Tag::factory()->create();
        $song->tags()->attach([$tag1->id, $tag2->id]);

        $response = $this->actingAs($this->user)->deleteJson("/api/v1/songs/{$song->id}/tags", [
            'tag_ids' => [$tag1->id],
        ]);

        $response->assertOk()
            ->assertJsonCount(1, 'data.tags');
    }

    public function test_unauthenticated_cannot_access_tags(): void
    {
        $response = $this->getJson('/api/v1/tags');

        $response->assertUnauthorized();
    }

    public function test_update_tag_parent_cascades_depth_to_descendants(): void
    {
        $root = Tag::factory()->create(['name' => 'Root', 'depth' => 1, 'parent_id' => null]);
        $child = Tag::factory()->create(['name' => 'Child', 'depth' => 2, 'parent_id' => $root->id]);
        $grandchild = Tag::factory()->create(['name' => 'Grandchild', 'depth' => 3, 'parent_id' => $child->id]);

        // Create a new parent
        $newParent = Tag::factory()->create(['name' => 'NewParent', 'depth' => 1, 'parent_id' => null]);

        // Move child under newParent
        $response = $this->actingAs($this->user)->putJson("/api/v1/tags/{$child->id}", [
            'parent_id' => $newParent->id,
        ]);

        $response->assertOk();

        // Verify depths are updated
        $this->assertEquals(2, $child->fresh()->depth);
        $this->assertEquals(3, $grandchild->fresh()->depth);
    }

    public function test_cannot_set_tag_parent_to_itself(): void
    {
        $tag = Tag::factory()->create();

        $response = $this->actingAs($this->user)->putJson("/api/v1/tags/{$tag->id}", [
            'parent_id' => $tag->id,
        ]);

        $response->assertStatus(422);
    }

    public function test_cannot_set_tag_parent_to_descendant(): void
    {
        $parent = Tag::factory()->create();
        $child = Tag::factory()->create(['parent_id' => $parent->id]);

        $response = $this->actingAs($this->user)->putJson("/api/v1/tags/{$parent->id}", [
            'parent_id' => $child->id,
        ]);

        $response->assertStatus(422);
    }
}

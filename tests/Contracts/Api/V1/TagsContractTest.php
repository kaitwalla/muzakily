<?php

declare(strict_types=1);

namespace Tests\Contracts\Api\V1;

use App\Http\Resources\Api\V1\TagResource;
use App\Models\Song;
use App\Models\Tag;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class TagsContractTest extends TestCase
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

    public function test_index_returns_correct_structure(): void
    {
        Tag::factory()->count(3)->create();

        $response = $this->actingAs($this->user)->getJson('/api/v1/tags');

        $response->assertOk()
            ->assertJsonStructure([
                'data' => [
                    '*' => TagResource::$jsonStructure,
                ],
            ]);
    }

    public function test_index_nested_includes_children_structure(): void
    {
        $parent = Tag::factory()->create();
        Tag::factory()->create(['parent_id' => $parent->id]);

        $response = $this->actingAs($this->user)->getJson('/api/v1/tags');

        $response->assertOk()
            ->assertJsonStructure([
                'data' => [
                    '*' => [
                        ...TagResource::$jsonStructure,
                        'children' => [
                            '*' => TagResource::$jsonStructure,
                        ],
                    ],
                ],
            ]);
    }

    public function test_show_returns_correct_structure(): void
    {
        $tag = Tag::factory()->create();

        $response = $this->actingAs($this->user)->getJson("/api/v1/tags/{$tag->id}");

        $response->assertOk()
            ->assertJsonStructure([
                'data' => TagResource::$jsonStructure,
            ]);
    }

    public function test_create_returns_correct_structure(): void
    {
        $response = $this->actingAs($this->admin)->postJson('/api/v1/tags', [
            'name' => 'New Tag',
            'color' => '#e74c3c',
        ]);

        $response->assertCreated()
            ->assertJsonStructure([
                'data' => TagResource::$jsonStructure,
            ]);
    }

    public function test_update_returns_correct_structure(): void
    {
        $tag = Tag::factory()->create();

        $response = $this->actingAs($this->admin)->putJson("/api/v1/tags/{$tag->id}", [
            'name' => 'Updated Tag',
        ]);

        $response->assertOk()
            ->assertJsonStructure([
                'data' => TagResource::$jsonStructure,
            ]);
    }

    public function test_songs_returns_correct_structure(): void
    {
        $tag = Tag::factory()->create();
        $song = Song::factory()->create();
        $tag->songs()->attach($song);

        $response = $this->actingAs($this->user)->getJson("/api/v1/tags/{$tag->id}/songs");

        // Use base structure without optional relations (smart_folder, tags)
        $baseStructure = [
            'id',
            'title',
            'artist_id',
            'artist_name',
            'artist_slug',
            'album_id',
            'album_name',
            'album_slug',
            'album_cover',
            'length',
            'track',
            'disc',
            'year',
            'genre',
            'audio_format',
            'is_favorite',
            'play_count',
            'created_at',
        ];

        $response->assertOk()
            ->assertJsonStructure([
                'data' => [
                    '*' => $baseStructure,
                ],
                'links',
                'meta',
            ]);
    }

    public function test_add_tags_to_song_returns_correct_structure(): void
    {
        $song = Song::factory()->create();
        $tag = Tag::factory()->create();

        $response = $this->actingAs($this->user)->postJson("/api/v1/songs/{$song->id}/tags", [
            'tag_ids' => [$tag->id],
        ]);

        $response->assertOk()
            ->assertJsonStructure([
                'data' => [
                    'song_id',
                    'tags' => [
                        '*' => ['id', 'name', 'slug'],
                    ],
                ],
            ]);
    }

    public function test_error_response_structure(): void
    {
        $response = $this->actingAs($this->user)->getJson('/api/v1/tags/99999');

        $response->assertNotFound()
            ->assertJsonStructure([
                'message',
            ]);
    }

    public function test_validation_error_structure(): void
    {
        $response = $this->actingAs($this->admin)->postJson('/api/v1/tags', []);

        $response->assertUnprocessable()
            ->assertJsonStructure([
                'message',
                'errors',
            ]);
    }
}

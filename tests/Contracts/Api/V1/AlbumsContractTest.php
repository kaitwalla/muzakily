<?php

declare(strict_types=1);

namespace Tests\Contracts\Api\V1;

use App\Http\Resources\Api\V1\AlbumResource;
use App\Http\Resources\Api\V1\SongResource;
use App\Models\Album;
use App\Models\Artist;
use App\Models\Song;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AlbumsContractTest extends TestCase
{
    use RefreshDatabase;

    private User $user;

    protected function setUp(): void
    {
        parent::setUp();
        $this->user = User::factory()->create(['role' => 'user']);
    }

    public function test_index_returns_correct_structure(): void
    {
        Album::factory()->count(3)->create();

        $response = $this->actingAs($this->user)->getJson('/api/v1/albums');

        $response->assertOk()
            ->assertJsonStructure([
                'data' => [
                    '*' => AlbumResource::$jsonStructure,
                ],
                'links',
                'meta',
            ]);
    }

    public function test_index_returns_uuid_as_id(): void
    {
        $album = Album::factory()->create();

        $response = $this->actingAs($this->user)->getJson('/api/v1/albums');

        $response->assertOk();

        $data = $response->json('data.0');
        $this->assertEquals($album->uuid, $data['id']);
        $this->assertIsString($data['id']);
    }

    public function test_index_returns_name_not_title(): void
    {
        Album::factory()->create(['name' => 'Test Album Name']);

        $response = $this->actingAs($this->user)->getJson('/api/v1/albums');

        $response->assertOk();

        $data = $response->json('data.0');
        $this->assertArrayHasKey('name', $data);
        $this->assertArrayNotHasKey('title', $data);
        $this->assertEquals('Test Album Name', $data['name']);
    }

    public function test_index_returns_cover_not_cover_url(): void
    {
        Album::factory()->create(['cover' => 'https://example.com/cover.jpg']);

        $response = $this->actingAs($this->user)->getJson('/api/v1/albums');

        $response->assertOk();

        $data = $response->json('data.0');
        $this->assertArrayHasKey('cover', $data);
        $this->assertArrayNotHasKey('cover_url', $data);
    }

    public function test_index_returns_year_not_release_date(): void
    {
        Album::factory()->create(['year' => 2023]);

        $response = $this->actingAs($this->user)->getJson('/api/v1/albums');

        $response->assertOk();

        $data = $response->json('data.0');
        $this->assertArrayHasKey('year', $data);
        $this->assertArrayNotHasKey('release_date', $data);
        $this->assertEquals(2023, $data['year']);
    }

    public function test_index_returns_flat_artist_fields(): void
    {
        $artist = Artist::factory()->create(['name' => 'Test Artist']);
        Album::factory()->create(['artist_id' => $artist->id]);

        $response = $this->actingAs($this->user)->getJson('/api/v1/albums');

        $response->assertOk();

        $data = $response->json('data.0');
        $this->assertArrayHasKey('artist_id', $data);
        $this->assertArrayHasKey('artist_name', $data);
        $this->assertArrayNotHasKey('artist', $data);
        $this->assertEquals($artist->uuid, $data['artist_id']);
        $this->assertEquals('Test Artist', $data['artist_name']);
    }

    public function test_show_returns_correct_structure(): void
    {
        $album = Album::factory()->create();

        $response = $this->actingAs($this->user)->getJson("/api/v1/albums/{$album->uuid}");

        $response->assertOk()
            ->assertJsonStructure([
                'data' => AlbumResource::$jsonStructure,
            ]);
    }

    public function test_show_can_be_accessed_by_uuid(): void
    {
        $album = Album::factory()->create();

        $response = $this->actingAs($this->user)->getJson("/api/v1/albums/{$album->uuid}");

        $response->assertOk()
            ->assertJsonPath('data.id', $album->uuid);
    }

    public function test_songs_returns_correct_structure(): void
    {
        $album = Album::factory()->create();
        Song::factory()->count(3)->create(['album_id' => $album->id]);

        $response = $this->actingAs($this->user)->getJson("/api/v1/albums/{$album->uuid}/songs");

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
            ]);
    }

    public function test_songs_returns_artist_and_album_slugs(): void
    {
        $artist = Artist::factory()->create();
        $album = Album::factory()->create(['artist_id' => $artist->id]);
        Song::factory()->create([
            'artist_id' => $artist->id,
            'album_id' => $album->id,
        ]);

        $response = $this->actingAs($this->user)->getJson("/api/v1/albums/{$album->uuid}/songs");

        $response->assertOk();

        $song = $response->json('data.0');
        $this->assertEquals($artist->uuid, $song['artist_slug']);
        $this->assertEquals($album->uuid, $song['album_slug']);
    }

    public function test_songs_ordered_by_disc_then_track(): void
    {
        $album = Album::factory()->create();
        Song::factory()->create(['album_id' => $album->id, 'disc' => 2, 'track' => 1]);
        Song::factory()->create(['album_id' => $album->id, 'disc' => 1, 'track' => 2]);
        Song::factory()->create(['album_id' => $album->id, 'disc' => 1, 'track' => 1]);

        $response = $this->actingAs($this->user)->getJson("/api/v1/albums/{$album->uuid}/songs");

        $response->assertOk();

        $songs = $response->json('data');
        $this->assertEquals(1, $songs[0]['disc']);
        $this->assertEquals(1, $songs[0]['track']);
        $this->assertEquals(1, $songs[1]['disc']);
        $this->assertEquals(2, $songs[1]['track']);
        $this->assertEquals(2, $songs[2]['disc']);
        $this->assertEquals(1, $songs[2]['track']);
    }

    public function test_error_response_structure(): void
    {
        // Use a valid UUID format that doesn't exist in the database
        $nonExistentUuid = '00000000-0000-0000-0000-000000000000';

        $response = $this->actingAs($this->user)->getJson("/api/v1/albums/{$nonExistentUuid}");

        $response->assertNotFound()
            ->assertJsonStructure([
                'message',
            ]);
    }
}

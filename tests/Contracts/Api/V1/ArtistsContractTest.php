<?php

declare(strict_types=1);

namespace Tests\Contracts\Api\V1;

use App\Http\Resources\Api\V1\AlbumResource;
use App\Http\Resources\Api\V1\ArtistResource;
use App\Http\Resources\Api\V1\SongResource;
use App\Models\Album;
use App\Models\Artist;
use App\Models\Song;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ArtistsContractTest extends TestCase
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
        Artist::factory()->count(3)->create();

        $response = $this->actingAs($this->user)->getJson('/api/v1/artists');

        $response->assertOk()
            ->assertJsonStructure([
                'data' => [
                    '*' => ArtistResource::$jsonStructure,
                ],
                'links',
                'meta',
            ]);
    }

    public function test_index_returns_uuid_as_id(): void
    {
        $artist = Artist::factory()->create();

        $response = $this->actingAs($this->user)->getJson('/api/v1/artists');

        $response->assertOk();

        $data = $response->json('data.0');
        $this->assertEquals($artist->uuid, $data['id']);
        $this->assertIsString($data['id']);
    }

    public function test_index_returns_image_not_image_url(): void
    {
        Artist::factory()->create(['image' => 'https://example.com/image.jpg']);

        $response = $this->actingAs($this->user)->getJson('/api/v1/artists');

        $response->assertOk();

        $data = $response->json('data.0');
        $this->assertArrayHasKey('image', $data);
        $this->assertArrayNotHasKey('image_url', $data);
    }

    public function test_index_returns_counts(): void
    {
        $artist = Artist::factory()->create();
        // Create albums for this specific artist
        Album::factory()->count(2)->for($artist)->create();
        // Create songs for this specific artist (no album to avoid factory creating new artists)
        Song::factory()->count(5)->create([
            'artist_id' => $artist->id,
            'album_id' => null,
        ]);

        $response = $this->actingAs($this->user)->getJson('/api/v1/artists');

        $response->assertOk();

        // Find the artist we created in the response
        $artists = collect($response->json('data'));
        $artistData = $artists->firstWhere('id', $artist->uuid);

        $this->assertNotNull($artistData);
        $this->assertArrayHasKey('album_count', $artistData);
        $this->assertArrayHasKey('song_count', $artistData);
        $this->assertEquals(2, $artistData['album_count']);
        $this->assertEquals(5, $artistData['song_count']);
    }

    public function test_show_returns_correct_structure(): void
    {
        $artist = Artist::factory()->create();

        $response = $this->actingAs($this->user)->getJson("/api/v1/artists/{$artist->uuid}");

        $response->assertOk()
            ->assertJsonStructure([
                'data' => ArtistResource::$jsonStructure,
            ]);
    }

    public function test_show_can_be_accessed_by_uuid(): void
    {
        $artist = Artist::factory()->create();

        $response = $this->actingAs($this->user)->getJson("/api/v1/artists/{$artist->uuid}");

        $response->assertOk()
            ->assertJsonPath('data.id', $artist->uuid);
    }

    public function test_show_includes_bio_and_musicbrainz_id(): void
    {
        $artist = Artist::factory()->create([
            'bio' => 'Artist biography text',
            'musicbrainz_id' => 'abc-123-def',
        ]);

        $response = $this->actingAs($this->user)->getJson("/api/v1/artists/{$artist->uuid}");

        $response->assertOk()
            ->assertJsonPath('data.bio', 'Artist biography text')
            ->assertJsonPath('data.musicbrainz_id', 'abc-123-def');
    }

    public function test_albums_returns_correct_structure(): void
    {
        $artist = Artist::factory()->create();
        Album::factory()->count(3)->create(['artist_id' => $artist->id]);

        $response = $this->actingAs($this->user)->getJson("/api/v1/artists/{$artist->uuid}/albums");

        $response->assertOk()
            ->assertJsonStructure([
                'data' => [
                    '*' => AlbumResource::$jsonStructure,
                ],
            ]);
    }

    public function test_albums_returns_uuid_as_id(): void
    {
        $artist = Artist::factory()->create();
        $album = Album::factory()->create(['artist_id' => $artist->id]);

        $response = $this->actingAs($this->user)->getJson("/api/v1/artists/{$artist->uuid}/albums");

        $response->assertOk();

        $data = $response->json('data.0');
        $this->assertEquals($album->uuid, $data['id']);
    }

    public function test_songs_returns_correct_structure(): void
    {
        $artist = Artist::factory()->create();
        Song::factory()->count(3)->create(['artist_id' => $artist->id]);

        $response = $this->actingAs($this->user)->getJson("/api/v1/artists/{$artist->uuid}/songs");

        // Use base structure without optional relations (smart_folder, tags)
        $baseSongStructure = [
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
                    '*' => $baseSongStructure,
                ],
                'links',
                'meta',
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

        $response = $this->actingAs($this->user)->getJson("/api/v1/artists/{$artist->uuid}/songs");

        $response->assertOk();

        $song = $response->json('data.0');
        $this->assertEquals($artist->uuid, $song['artist_slug']);
        $this->assertEquals($album->uuid, $song['album_slug']);
    }

    public function test_error_response_structure(): void
    {
        // Use a valid UUID format that doesn't exist in the database
        $nonExistentUuid = '00000000-0000-0000-0000-000000000000';

        $response = $this->actingAs($this->user)->getJson("/api/v1/artists/{$nonExistentUuid}");

        $response->assertNotFound()
            ->assertJsonStructure([
                'message',
            ]);
    }
}

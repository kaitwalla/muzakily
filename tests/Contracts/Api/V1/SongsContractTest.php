<?php

declare(strict_types=1);

namespace Tests\Contracts\Api\V1;

use App\Http\Resources\Api\V1\SongResource;
use App\Models\Album;
use App\Models\Artist;
use App\Models\Song;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SongsContractTest extends TestCase
{
    use RefreshDatabase;

    private User $user;

    protected function setUp(): void
    {
        parent::setUp();
        $this->user = User::factory()->create(['role' => 'user']);
    }

    /**
     * Base song structure without optional relations (smart_folder, tags).
     *
     * @var array<string>
     */
    private static array $baseSongStructure = [
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

    public function test_index_returns_correct_structure(): void
    {
        Song::factory()->count(3)->create();

        $response = $this->actingAs($this->user)->getJson('/api/v1/songs');

        $response->assertOk()
            ->assertJsonStructure([
                'data' => [
                    '*' => self::$baseSongStructure,
                ],
                'links',
                'meta',
            ]);
    }

    public function test_index_returns_artist_and_album_slugs(): void
    {
        $artist = Artist::factory()->create();
        $album = Album::factory()->create(['artist_id' => $artist->id]);
        Song::factory()->create([
            'artist_id' => $artist->id,
            'album_id' => $album->id,
        ]);

        $response = $this->actingAs($this->user)->getJson('/api/v1/songs');

        $response->assertOk()
            ->assertJsonStructure([
                'data' => [
                    '*' => [
                        'artist_slug',
                        'album_slug',
                    ],
                ],
            ]);

        $song = $response->json('data.0');
        $this->assertEquals($artist->uuid, $song['artist_slug']);
        $this->assertEquals($album->uuid, $song['album_slug']);
    }

    public function test_index_returns_null_slugs_for_songs_without_relations(): void
    {
        Song::factory()->create([
            'artist_id' => null,
            'album_id' => null,
        ]);

        $response = $this->actingAs($this->user)->getJson('/api/v1/songs');

        $response->assertOk();

        $song = $response->json('data.0');
        $this->assertNull($song['artist_slug']);
        $this->assertNull($song['album_slug']);
    }

    public function test_show_returns_correct_structure(): void
    {
        $song = Song::factory()->create();

        $response = $this->actingAs($this->user)->getJson("/api/v1/songs/{$song->id}");

        $response->assertOk()
            ->assertJsonStructure([
                'data' => self::$baseSongStructure,
            ]);
    }

    public function test_show_returns_artist_and_album_slugs(): void
    {
        $artist = Artist::factory()->create();
        $album = Album::factory()->create(['artist_id' => $artist->id]);
        $song = Song::factory()->create([
            'artist_id' => $artist->id,
            'album_id' => $album->id,
        ]);

        $response = $this->actingAs($this->user)->getJson("/api/v1/songs/{$song->id}");

        $response->assertOk()
            ->assertJsonPath('data.artist_slug', $artist->uuid)
            ->assertJsonPath('data.album_slug', $album->uuid);
    }

    public function test_song_slug_matches_artist_id(): void
    {
        $artist = Artist::factory()->create();
        $song = Song::factory()->create(['artist_id' => $artist->id]);

        $response = $this->actingAs($this->user)->getJson("/api/v1/songs/{$song->id}");

        $response->assertOk();

        $data = $response->json('data');
        $this->assertEquals($data['artist_id'], $data['artist_slug']);
    }

    public function test_song_slug_matches_album_id(): void
    {
        $album = Album::factory()->create();
        $song = Song::factory()->create(['album_id' => $album->id]);

        $response = $this->actingAs($this->user)->getJson("/api/v1/songs/{$song->id}");

        $response->assertOk();

        $data = $response->json('data');
        $this->assertEquals($data['album_id'], $data['album_slug']);
    }

    public function test_recently_played_returns_slugs(): void
    {
        $artist = Artist::factory()->create();
        $album = Album::factory()->create(['artist_id' => $artist->id]);
        $song = Song::factory()->create([
            'artist_id' => $artist->id,
            'album_id' => $album->id,
        ]);

        // Record a play
        $this->actingAs($this->user)->postJson('/api/v1/interactions/play', [
            'song_id' => $song->id,
        ]);

        $response = $this->actingAs($this->user)->getJson('/api/v1/songs/recently-played');

        $response->assertOk()
            ->assertJsonStructure([
                'data' => [
                    '*' => [
                        'artist_slug',
                        'album_slug',
                    ],
                ],
            ]);
    }

    public function test_error_response_structure(): void
    {
        $response = $this->actingAs($this->user)->getJson('/api/v1/songs/99999');

        $response->assertNotFound()
            ->assertJsonStructure([
                'message',
            ]);
    }
}

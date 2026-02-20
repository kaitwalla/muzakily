<?php

declare(strict_types=1);

namespace Tests\Contracts\Api\V1;

use App\Models\Playlist;
use App\Models\Song;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PlaylistsContractTest extends TestCase
{
    use RefreshDatabase;

    private User $user;

    protected function setUp(): void
    {
        parent::setUp();
        $this->user = User::factory()->create();
    }

    /**
     * Expected playlist structure.
     *
     * @var array<string>
     */
    private static array $playlistStructure = [
        'id',
        'name',
        'description',
        'is_smart',
        'is_public',
        'cover_url',
        'songs_count',
        'created_at',
        'updated_at',
    ];

    /**
     * Expected song structure within playlist songs response.
     *
     * @var array<string>
     */
    private static array $songStructure = [
        'id',
        'title',
        'artist_id',
        'artist_name',
        'album_id',
        'album_name',
        'album_cover',
        'length',
    ];

    public function test_index_returns_correct_structure(): void
    {
        Playlist::factory()->count(2)->create(['user_id' => $this->user->id]);

        $response = $this->actingAs($this->user)->getJson('/api/v1/playlists');

        $response->assertOk()
            ->assertJsonStructure([
                'data' => [
                    '*' => self::$playlistStructure,
                ],
            ]);
    }

    public function test_show_returns_correct_structure(): void
    {
        $playlist = Playlist::factory()->create(['user_id' => $this->user->id]);

        $response = $this->actingAs($this->user)->getJson("/api/v1/playlists/{$playlist->id}");

        $response->assertOk()
            ->assertJsonStructure([
                'data' => self::$playlistStructure,
            ]);
    }

    public function test_songs_returns_correct_structure(): void
    {
        $playlist = Playlist::factory()->create(['user_id' => $this->user->id]);
        $songs = Song::factory()->count(3)->create();

        foreach ($songs as $index => $song) {
            $playlist->songs()->attach($song->id, ['position' => $index]);
        }

        $response = $this->actingAs($this->user)->getJson("/api/v1/playlists/{$playlist->id}/songs");

        $response->assertOk()
            ->assertJsonStructure([
                'data' => [
                    '*' => self::$songStructure,
                ],
                'links',
                'meta',
            ]);
    }

    /**
     * Verify pagination metadata structure for playlist songs.
     * Both web and mobile clients depend on this exact structure.
     */
    public function test_songs_returns_standard_pagination_metadata(): void
    {
        $playlist = Playlist::factory()->create(['user_id' => $this->user->id]);
        $songs = Song::factory()->count(5)->create();

        foreach ($songs as $index => $song) {
            $playlist->songs()->attach($song->id, ['position' => $index]);
        }

        $response = $this->actingAs($this->user)->getJson("/api/v1/playlists/{$playlist->id}/songs");

        $response->assertOk()
            ->assertJsonStructure([
                'meta' => [
                    'current_page',
                    'from',
                    'last_page',
                    'per_page',
                    'to',
                    'total',
                ],
            ]);
    }

    public function test_songs_pagination_values_are_correct(): void
    {
        $playlist = Playlist::factory()->create(['user_id' => $this->user->id]);
        $songs = Song::factory()->count(75)->create();

        foreach ($songs as $index => $song) {
            $playlist->songs()->attach($song->id, ['position' => $index]);
        }

        $response = $this->actingAs($this->user)->getJson("/api/v1/playlists/{$playlist->id}/songs?per_page=25&page=2");

        $response->assertOk()
            ->assertJsonPath('meta.current_page', 2)
            ->assertJsonPath('meta.per_page', 25)
            ->assertJsonPath('meta.last_page', 3)
            ->assertJsonPath('meta.total', 75)
            ->assertJsonCount(25, 'data');
    }

    public function test_smart_playlist_returns_correct_structure(): void
    {
        $playlist = Playlist::factory()->create([
            'user_id' => $this->user->id,
            'is_smart' => true,
            'rules' => [['logic' => 'and', 'rules' => [['field' => 'title', 'operator' => 'contains', 'value' => 'Test']]]],
        ]);

        $response = $this->actingAs($this->user)->getJson("/api/v1/playlists/{$playlist->id}");

        $response->assertOk()
            ->assertJsonStructure([
                'data' => array_merge(self::$playlistStructure, ['rules']),
            ]);
    }
}

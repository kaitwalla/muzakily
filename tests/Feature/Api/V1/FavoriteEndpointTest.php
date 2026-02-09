<?php

declare(strict_types=1);

namespace Tests\Feature\Api\V1;

use App\Models\Album;
use App\Models\Artist;
use App\Models\Favorite;
use App\Models\Playlist;
use App\Models\Song;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class FavoriteEndpointTest extends TestCase
{
    use RefreshDatabase;

    private User $user;

    protected function setUp(): void
    {
        parent::setUp();
        $this->user = User::factory()->create();
    }

    public function test_list_favorites_requires_authentication(): void
    {
        $response = $this->getJson('/api/v1/favorites');

        $response->assertUnauthorized();
    }

    public function test_list_all_favorites(): void
    {
        $artist = Artist::factory()->create();
        $album = Album::factory()->create(['artist_id' => $artist->id]);
        $song = Song::factory()->create(['artist_id' => $artist->id, 'album_id' => $album->id]);
        $playlist = Playlist::factory()->create(['user_id' => $this->user->id]);

        Favorite::add($this->user, $song);
        Favorite::add($this->user, $album);
        Favorite::add($this->user, $artist);
        Favorite::add($this->user, $playlist);

        $response = $this->actingAs($this->user)->getJson('/api/v1/favorites');

        $response->assertOk()
            ->assertJsonStructure([
                'data' => [
                    'songs',
                    'albums',
                    'artists',
                    'playlists',
                ],
            ]);
    }

    public function test_list_favorites_by_type_song(): void
    {
        $artist = Artist::factory()->create();
        $album = Album::factory()->create(['artist_id' => $artist->id]);
        $song = Song::factory()->create(['artist_id' => $artist->id, 'album_id' => $album->id]);

        Favorite::add($this->user, $song);
        Favorite::add($this->user, $artist);

        $response = $this->actingAs($this->user)->getJson('/api/v1/favorites?type=song');

        $response->assertOk()
            ->assertJsonCount(1, 'data.songs')
            ->assertJsonMissing(['albums'])
            ->assertJsonMissing(['artists']);
    }

    public function test_list_favorites_by_type_album(): void
    {
        $artist = Artist::factory()->create();
        $album = Album::factory()->create(['artist_id' => $artist->id]);

        Favorite::add($this->user, $album);

        $response = $this->actingAs($this->user)->getJson('/api/v1/favorites?type=album');

        $response->assertOk()
            ->assertJsonCount(1, 'data.albums');
    }

    public function test_list_favorites_by_type_artist(): void
    {
        $artist = Artist::factory()->create();

        Favorite::add($this->user, $artist);

        $response = $this->actingAs($this->user)->getJson('/api/v1/favorites?type=artist');

        $response->assertOk()
            ->assertJsonCount(1, 'data.artists');
    }

    public function test_list_favorites_by_type_playlist(): void
    {
        $playlist = Playlist::factory()->create(['user_id' => $this->user->id]);

        Favorite::add($this->user, $playlist);

        $response = $this->actingAs($this->user)->getJson('/api/v1/favorites?type=playlist');

        $response->assertOk()
            ->assertJsonCount(1, 'data.playlists');
    }

    public function test_add_song_to_favorites(): void
    {
        $artist = Artist::factory()->create();
        $album = Album::factory()->create(['artist_id' => $artist->id]);
        $song = Song::factory()->create(['artist_id' => $artist->id, 'album_id' => $album->id]);

        $response = $this->actingAs($this->user)->postJson('/api/v1/favorites', [
            'type' => 'song',
            'id' => $song->id,
        ]);

        $response->assertCreated()
            ->assertJsonPath('data.favorited', true);

        $this->assertTrue(Favorite::isFavorited($this->user, $song));
    }

    public function test_add_album_to_favorites(): void
    {
        $artist = Artist::factory()->create();
        $album = Album::factory()->create(['artist_id' => $artist->id]);

        $response = $this->actingAs($this->user)->postJson('/api/v1/favorites', [
            'type' => 'album',
            'id' => $album->uuid,
        ]);

        $response->assertCreated()
            ->assertJsonPath('data.favorited', true);

        $this->assertTrue(Favorite::isFavorited($this->user, $album));
    }

    public function test_add_artist_to_favorites(): void
    {
        $artist = Artist::factory()->create();

        $response = $this->actingAs($this->user)->postJson('/api/v1/favorites', [
            'type' => 'artist',
            'id' => $artist->uuid,
        ]);

        $response->assertCreated()
            ->assertJsonPath('data.favorited', true);

        $this->assertTrue(Favorite::isFavorited($this->user, $artist));
    }

    public function test_add_playlist_to_favorites(): void
    {
        $playlist = Playlist::factory()->create(['user_id' => $this->user->id]);

        $response = $this->actingAs($this->user)->postJson('/api/v1/favorites', [
            'type' => 'playlist',
            'id' => $playlist->id,
        ]);

        $response->assertCreated()
            ->assertJsonPath('data.favorited', true);

        $this->assertTrue(Favorite::isFavorited($this->user, $playlist));
    }

    public function test_add_favorite_validates_type(): void
    {
        $response = $this->actingAs($this->user)->postJson('/api/v1/favorites', [
            'type' => 'invalid',
            'id' => 'some-id',
        ]);

        $response->assertUnprocessable()
            ->assertJsonValidationErrors(['type']);
    }

    public function test_add_favorite_validates_id_required(): void
    {
        $response = $this->actingAs($this->user)->postJson('/api/v1/favorites', [
            'type' => 'song',
        ]);

        $response->assertUnprocessable()
            ->assertJsonValidationErrors(['id']);
    }

    public function test_add_favorite_returns_404_for_nonexistent(): void
    {
        $response = $this->actingAs($this->user)->postJson('/api/v1/favorites', [
            'type' => 'song',
            'id' => '00000000-0000-0000-0000-000000000000',
        ]);

        $response->assertNotFound();
    }

    public function test_remove_song_from_favorites(): void
    {
        $artist = Artist::factory()->create();
        $album = Album::factory()->create(['artist_id' => $artist->id]);
        $song = Song::factory()->create(['artist_id' => $artist->id, 'album_id' => $album->id]);
        Favorite::add($this->user, $song);

        $response = $this->actingAs($this->user)->deleteJson('/api/v1/favorites', [
            'type' => 'song',
            'id' => $song->id,
        ]);

        $response->assertNoContent();

        $this->assertFalse(Favorite::isFavorited($this->user, $song));
    }

    public function test_remove_album_from_favorites(): void
    {
        $artist = Artist::factory()->create();
        $album = Album::factory()->create(['artist_id' => $artist->id]);
        Favorite::add($this->user, $album);

        $response = $this->actingAs($this->user)->deleteJson('/api/v1/favorites', [
            'type' => 'album',
            'id' => $album->uuid,
        ]);

        $response->assertNoContent();

        $this->assertFalse(Favorite::isFavorited($this->user, $album));
    }

    public function test_favorites_are_user_specific(): void
    {
        $otherUser = User::factory()->create();
        $artist = Artist::factory()->create();
        $album = Album::factory()->create(['artist_id' => $artist->id]);
        $song = Song::factory()->create(['artist_id' => $artist->id, 'album_id' => $album->id]);

        Favorite::add($this->user, $song);
        Favorite::add($otherUser, $song);

        $response = $this->actingAs($this->user)->getJson('/api/v1/favorites?type=song');

        $response->assertOk()
            ->assertJsonCount(1, 'data.songs');

        // Removing from one user doesn't affect the other
        Favorite::remove($this->user, $song);

        $this->assertFalse(Favorite::isFavorited($this->user, $song));
        $this->assertTrue(Favorite::isFavorited($otherUser, $song));
    }

    public function test_adding_duplicate_favorite_is_idempotent(): void
    {
        $artist = Artist::factory()->create();
        $album = Album::factory()->create(['artist_id' => $artist->id]);
        $song = Song::factory()->create(['artist_id' => $artist->id, 'album_id' => $album->id]);

        // Add twice
        $this->actingAs($this->user)->postJson('/api/v1/favorites', [
            'type' => 'song',
            'id' => $song->id,
        ]);

        $response = $this->actingAs($this->user)->postJson('/api/v1/favorites', [
            'type' => 'song',
            'id' => $song->id,
        ]);

        $response->assertCreated();

        // Should only have one favorite entry
        $this->assertEquals(1, Favorite::where('user_id', $this->user->id)->count());
    }
}

<?php

declare(strict_types=1);

namespace Tests\Feature\Api\V1;

use App\Models\Album;
use App\Models\Artist;
use App\Models\Genre;
use App\Models\Song;
use App\Models\Tag;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SearchMeilisearchTest extends TestCase
{
    use RefreshDatabase;

    private User $user;

    protected function setUp(): void
    {
        parent::setUp();
        $this->user = User::factory()->create();
    }

    public function test_search_requires_authentication(): void
    {
        $response = $this->getJson('/api/v1/search?q=test');

        $response->assertUnauthorized();
    }

    public function test_search_requires_query_parameter(): void
    {
        $response = $this->actingAs($this->user)->getJson('/api/v1/search');

        $response->assertUnprocessable()
            ->assertJsonValidationErrors(['q']);
    }

    public function test_search_requires_minimum_query_length(): void
    {
        $response = $this->actingAs($this->user)->getJson('/api/v1/search?q=a');

        $response->assertUnprocessable()
            ->assertJsonValidationErrors(['q']);
    }

    public function test_search_returns_songs_albums_artists(): void
    {
        $artist = Artist::factory()->create(['name' => 'Christmas Band']);
        $album = Album::factory()->create([
            'name' => 'Christmas Album',
            'artist_id' => $artist->id,
        ]);
        Song::factory()->create([
            'title' => 'Christmas Song',
            'artist_id' => $artist->id,
            'album_id' => $album->id,
        ]);

        $response = $this->actingAs($this->user)->getJson('/api/v1/search?q=Christmas');

        $response->assertOk()
            ->assertJsonStructure([
                'data' => [
                    'songs' => ['data', 'total'],
                    'albums' => ['data', 'total'],
                    'artists' => ['data', 'total'],
                ],
                'meta' => ['query', 'engine'],
            ]);
    }

    public function test_search_filters_by_type_song(): void
    {
        $artist = Artist::factory()->create(['name' => 'Rock Band']);
        $album = Album::factory()->create([
            'name' => 'Rock Album',
            'artist_id' => $artist->id,
        ]);
        Song::factory()->create([
            'title' => 'Rock Song',
            'artist_id' => $artist->id,
            'album_id' => $album->id,
        ]);

        $response = $this->actingAs($this->user)->getJson('/api/v1/search?q=Rock&type=song');

        $response->assertOk()
            ->assertJsonStructure(['data' => ['songs']])
            ->assertJsonMissing(['albums', 'artists']);
    }

    public function test_search_filters_by_type_album(): void
    {
        $artist = Artist::factory()->create();
        Album::factory()->create([
            'name' => 'Jazz Album',
            'artist_id' => $artist->id,
        ]);

        $response = $this->actingAs($this->user)->getJson('/api/v1/search?q=Jazz&type=album');

        $response->assertOk()
            ->assertJsonStructure(['data' => ['albums']]);
    }

    public function test_search_filters_by_type_artist(): void
    {
        Artist::factory()->create(['name' => 'Blues Master']);

        $response = $this->actingAs($this->user)->getJson('/api/v1/search?q=Blues&type=artist');

        $response->assertOk()
            ->assertJsonStructure(['data' => ['artists']]);
    }

    public function test_search_filters_by_year(): void
    {
        $artist = Artist::factory()->create();
        $album = Album::factory()->create(['artist_id' => $artist->id]);

        Song::factory()->create([
            'title' => 'Old Hit',
            'year' => 2010,
            'artist_id' => $artist->id,
            'album_id' => $album->id,
        ]);

        Song::factory()->create([
            'title' => 'New Hit',
            'year' => 2023,
            'artist_id' => $artist->id,
            'album_id' => $album->id,
        ]);

        $response = $this->actingAs($this->user)
            ->getJson('/api/v1/search?q=Hit&filters[year]=2023');

        $response->assertOk();
        $songs = $response->json('data.songs.data');
        $this->assertCount(1, $songs);
        $this->assertEquals('New Hit', $songs[0]['title']);
    }

    public function test_search_filters_by_tag(): void
    {
        $artist = Artist::factory()->create();
        $album = Album::factory()->create(['artist_id' => $artist->id]);
        $tag = Tag::factory()->create(['name' => 'Holiday', 'slug' => 'holiday']);

        $taggedSong = Song::factory()->create([
            'title' => 'Holiday Song',
            'artist_id' => $artist->id,
            'album_id' => $album->id,
        ]);
        $taggedSong->tags()->attach($tag->id);

        Song::factory()->create([
            'title' => 'Regular Song',
            'artist_id' => $artist->id,
            'album_id' => $album->id,
        ]);

        $response = $this->actingAs($this->user)
            ->getJson('/api/v1/search?q=Song&filters[tag]=holiday');

        $response->assertOk();
        $songs = $response->json('data.songs.data');
        $this->assertCount(1, $songs);
        $this->assertEquals('Holiday Song', $songs[0]['title']);
    }

    public function test_search_filters_by_genre(): void
    {
        $artist = Artist::factory()->create();
        $album = Album::factory()->create(['artist_id' => $artist->id]);
        $genre = Genre::factory()->create(['name' => 'Electronic']);

        $electronicSong = Song::factory()->create([
            'title' => 'Electronic Beat',
            'artist_id' => $artist->id,
            'album_id' => $album->id,
        ]);
        $electronicSong->genres()->attach($genre->id);

        Song::factory()->create([
            'title' => 'Acoustic Beat',
            'artist_id' => $artist->id,
            'album_id' => $album->id,
        ]);

        $response = $this->actingAs($this->user)
            ->getJson('/api/v1/search?q=Beat&filters[genre]=Electronic');

        $response->assertOk();
        $songs = $response->json('data.songs.data');
        $this->assertCount(1, $songs);
        $this->assertEquals('Electronic Beat', $songs[0]['title']);
    }

    public function test_search_filters_by_format(): void
    {
        $artist = Artist::factory()->create();
        $album = Album::factory()->create(['artist_id' => $artist->id]);

        Song::factory()->create([
            'title' => 'Lossless Track',
            'audio_format' => \App\Enums\AudioFormat::FLAC,
            'artist_id' => $artist->id,
            'album_id' => $album->id,
        ]);

        Song::factory()->create([
            'title' => 'Compressed Track',
            'audio_format' => \App\Enums\AudioFormat::MP3,
            'artist_id' => $artist->id,
            'album_id' => $album->id,
        ]);

        $response = $this->actingAs($this->user)
            ->getJson('/api/v1/search?q=Track&filters[format]=flac');

        $response->assertOk();
        $songs = $response->json('data.songs.data');
        $this->assertCount(1, $songs);
        $this->assertEquals('Lossless Track', $songs[0]['title']);
    }

    public function test_search_respects_limit_parameter(): void
    {
        $artist = Artist::factory()->create();
        $album = Album::factory()->create(['artist_id' => $artist->id]);
        Song::factory()->count(20)->create([
            'title' => 'Test Song',
            'artist_id' => $artist->id,
            'album_id' => $album->id,
        ]);

        $response = $this->actingAs($this->user)
            ->getJson('/api/v1/search?q=Test&limit=5');

        $response->assertOk();
        $songs = $response->json('data.songs.data');
        $this->assertCount(5, $songs);
        $this->assertEquals(20, $response->json('data.songs.total'));
    }

    public function test_search_includes_meta_information(): void
    {
        $response = $this->actingAs($this->user)->getJson('/api/v1/search?q=anything');

        $response->assertOk()
            ->assertJsonPath('meta.query', 'anything')
            ->assertJsonStructure(['meta' => ['query', 'engine']]);
    }

    public function test_search_is_case_insensitive(): void
    {
        $artist = Artist::factory()->create();
        $album = Album::factory()->create(['artist_id' => $artist->id]);
        Song::factory()->create([
            'title' => 'UPPERCASE SONG',
            'artist_id' => $artist->id,
            'album_id' => $album->id,
        ]);

        $response1 = $this->actingAs($this->user)->getJson('/api/v1/search?q=uppercase');
        $response2 = $this->actingAs($this->user)->getJson('/api/v1/search?q=UPPERCASE');

        $response1->assertOk();
        $response2->assertOk();
        $this->assertEquals(
            $response1->json('data.songs.total'),
            $response2->json('data.songs.total')
        );
    }

    public function test_search_song_includes_tags(): void
    {
        $artist = Artist::factory()->create();
        $album = Album::factory()->create(['artist_id' => $artist->id]);
        $tag = Tag::factory()->create(['name' => 'Favorites']);
        $song = Song::factory()->create([
            'title' => 'Favorite Song',
            'artist_id' => $artist->id,
            'album_id' => $album->id,
        ]);
        $song->tags()->attach($tag->id);

        $response = $this->actingAs($this->user)->getJson('/api/v1/search?q=Favorite');

        $response->assertOk();
        $songData = $response->json('data.songs.data.0');
        $this->assertArrayHasKey('tags', $songData);
    }

    public function test_search_validates_type_parameter(): void
    {
        $response = $this->actingAs($this->user)
            ->getJson('/api/v1/search?q=test&type=invalid');

        $response->assertUnprocessable()
            ->assertJsonValidationErrors(['type']);
    }

    public function test_search_validates_limit_parameter(): void
    {
        $response = $this->actingAs($this->user)
            ->getJson('/api/v1/search?q=test&limit=1000');

        $response->assertUnprocessable()
            ->assertJsonValidationErrors(['limit']);
    }

    public function test_empty_search_returns_empty_results(): void
    {
        $response = $this->actingAs($this->user)
            ->getJson('/api/v1/search?q=nonexistentxyz123');

        $response->assertOk()
            ->assertJsonPath('data.songs.total', 0)
            ->assertJsonPath('data.albums.total', 0)
            ->assertJsonPath('data.artists.total', 0);
    }
}

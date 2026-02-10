<?php

declare(strict_types=1);

namespace Tests\Feature\Api\V1;

use App\Models\Album;
use App\Models\Artist;
use App\Models\Interaction;
use App\Models\Song;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class InteractionEndpointTest extends TestCase
{
    use RefreshDatabase;

    private User $user;

    protected function setUp(): void
    {
        parent::setUp();
        $this->user = User::factory()->create();
    }

    public function test_record_play_requires_authentication(): void
    {
        $response = $this->postJson('/api/v1/interactions/play', [
            'song_id' => '00000000-0000-0000-0000-000000000000',
        ]);

        $response->assertUnauthorized();
    }

    public function test_record_play_validates_song_id_required(): void
    {
        $response = $this->actingAs($this->user)->postJson('/api/v1/interactions/play', []);

        $response->assertUnprocessable()
            ->assertJsonValidationErrors(['song_id']);
    }

    public function test_record_play_validates_song_id_exists(): void
    {
        $response = $this->actingAs($this->user)->postJson('/api/v1/interactions/play', [
            'song_id' => '00000000-0000-0000-0000-000000000000',
        ]);

        $response->assertUnprocessable()
            ->assertJsonValidationErrors(['song_id']);
    }

    public function test_record_play_creates_interaction(): void
    {
        $artist = Artist::factory()->create();
        $album = Album::factory()->create(['artist_id' => $artist->id]);
        $song = Song::factory()->create(['artist_id' => $artist->id, 'album_id' => $album->id]);

        $response = $this->actingAs($this->user)->postJson('/api/v1/interactions/play', [
            'song_id' => $song->id,
        ]);

        $response->assertOk()
            ->assertJsonPath('data.song_id', $song->id)
            ->assertJsonPath('data.play_count', 1)
            ->assertJsonStructure(['data' => ['last_played_at']]);

        $this->assertDatabaseHas('interactions', [
            'user_id' => $this->user->id,
            'song_id' => $song->id,
            'play_count' => 1,
        ]);
    }

    public function test_record_play_increments_play_count(): void
    {
        $artist = Artist::factory()->create();
        $album = Album::factory()->create(['artist_id' => $artist->id]);
        $song = Song::factory()->create(['artist_id' => $artist->id, 'album_id' => $album->id]);

        // First play
        $this->actingAs($this->user)->postJson('/api/v1/interactions/play', [
            'song_id' => $song->id,
        ]);

        // Second play
        $response = $this->actingAs($this->user)->postJson('/api/v1/interactions/play', [
            'song_id' => $song->id,
        ]);

        $response->assertOk()
            ->assertJsonPath('data.play_count', 2);

        // Third play
        $response = $this->actingAs($this->user)->postJson('/api/v1/interactions/play', [
            'song_id' => $song->id,
        ]);

        $response->assertOk()
            ->assertJsonPath('data.play_count', 3);
    }

    public function test_record_play_updates_last_played_at(): void
    {
        $artist = Artist::factory()->create();
        $album = Album::factory()->create(['artist_id' => $artist->id]);
        $song = Song::factory()->create(['artist_id' => $artist->id, 'album_id' => $album->id]);

        $response1 = $this->actingAs($this->user)->postJson('/api/v1/interactions/play', [
            'song_id' => $song->id,
        ]);

        $firstPlayTime = $response1->json('data.last_played_at');

        // Wait a moment and play again
        sleep(1);

        $response2 = $this->actingAs($this->user)->postJson('/api/v1/interactions/play', [
            'song_id' => $song->id,
        ]);

        $secondPlayTime = $response2->json('data.last_played_at');

        $this->assertNotEquals($firstPlayTime, $secondPlayTime);
    }

    public function test_interactions_are_user_specific(): void
    {
        $otherUser = User::factory()->create();
        $artist = Artist::factory()->create();
        $album = Album::factory()->create(['artist_id' => $artist->id]);
        $song = Song::factory()->create(['artist_id' => $artist->id, 'album_id' => $album->id]);

        // User 1 plays 3 times
        for ($i = 0; $i < 3; $i++) {
            $this->actingAs($this->user)->postJson('/api/v1/interactions/play', [
                'song_id' => $song->id,
            ]);
        }

        // User 2 plays 2 times
        for ($i = 0; $i < 2; $i++) {
            $this->actingAs($otherUser)->postJson('/api/v1/interactions/play', [
                'song_id' => $song->id,
            ]);
        }

        // Check user 1's play count
        $interaction1 = Interaction::forUserAndSong($this->user, $song);
        $this->assertEquals(3, $interaction1->play_count);

        // Check user 2's play count
        $interaction2 = Interaction::forUserAndSong($otherUser, $song);
        $this->assertEquals(2, $interaction2->play_count);
    }

    public function test_record_play_returns_iso8601_timestamp(): void
    {
        $artist = Artist::factory()->create();
        $album = Album::factory()->create(['artist_id' => $artist->id]);
        $song = Song::factory()->create(['artist_id' => $artist->id, 'album_id' => $album->id]);

        $response = $this->actingAs($this->user)->postJson('/api/v1/interactions/play', [
            'song_id' => $song->id,
        ]);

        $lastPlayedAt = $response->json('data.last_played_at');

        // ISO 8601 format should parse correctly
        $parsed = \DateTime::createFromFormat(\DateTimeInterface::ISO8601, $lastPlayedAt);
        $this->assertInstanceOf(\DateTime::class, $parsed);
    }

    public function test_recently_played_requires_authentication(): void
    {
        $response = $this->getJson('/api/v1/songs/recently-played');

        $response->assertUnauthorized();
    }

    public function test_recently_played_returns_empty_for_new_user(): void
    {
        $response = $this->actingAs($this->user)->getJson('/api/v1/songs/recently-played');

        $response->assertOk()
            ->assertJsonPath('data', []);
    }

    public function test_recently_played_returns_songs_ordered_by_last_played(): void
    {
        $artist = Artist::factory()->create();
        $album = Album::factory()->create(['artist_id' => $artist->id]);
        $song1 = Song::factory()->create(['artist_id' => $artist->id, 'album_id' => $album->id, 'title' => 'Song 1']);
        $song2 = Song::factory()->create(['artist_id' => $artist->id, 'album_id' => $album->id, 'title' => 'Song 2']);
        $song3 = Song::factory()->create(['artist_id' => $artist->id, 'album_id' => $album->id, 'title' => 'Song 3']);

        // Create interactions with specific timestamps to ensure ordering
        Interaction::create([
            'user_id' => $this->user->id,
            'song_id' => $song1->id,
            'play_count' => 1,
            'last_played_at' => now()->subMinutes(3),
        ]);
        Interaction::create([
            'user_id' => $this->user->id,
            'song_id' => $song2->id,
            'play_count' => 1,
            'last_played_at' => now()->subMinutes(2),
        ]);
        Interaction::create([
            'user_id' => $this->user->id,
            'song_id' => $song3->id,
            'play_count' => 1,
            'last_played_at' => now()->subMinutes(1),
        ]);

        $response = $this->actingAs($this->user)->getJson('/api/v1/songs/recently-played');

        $response->assertOk()
            ->assertJsonCount(3, 'data');

        // Most recently played should be first
        $this->assertEquals('Song 3', $response->json('data.0.title'));
        $this->assertEquals('Song 2', $response->json('data.1.title'));
        $this->assertEquals('Song 1', $response->json('data.2.title'));
    }

    public function test_recently_played_respects_limit_parameter(): void
    {
        $artist = Artist::factory()->create();
        $album = Album::factory()->create(['artist_id' => $artist->id]);

        // Create and play 5 songs
        for ($i = 1; $i <= 5; $i++) {
            $song = Song::factory()->create(['artist_id' => $artist->id, 'album_id' => $album->id]);
            $this->actingAs($this->user)->postJson('/api/v1/interactions/play', ['song_id' => $song->id]);
        }

        $response = $this->actingAs($this->user)->getJson('/api/v1/songs/recently-played?limit=3');

        $response->assertOk()
            ->assertJsonCount(3, 'data');
    }

    public function test_recently_played_is_user_specific(): void
    {
        $otherUser = User::factory()->create();
        $artist = Artist::factory()->create();
        $album = Album::factory()->create(['artist_id' => $artist->id]);
        $song1 = Song::factory()->create(['artist_id' => $artist->id, 'album_id' => $album->id]);
        $song2 = Song::factory()->create(['artist_id' => $artist->id, 'album_id' => $album->id]);

        // User 1 plays song1
        $this->actingAs($this->user)->postJson('/api/v1/interactions/play', ['song_id' => $song1->id]);

        // User 2 plays song2
        $this->actingAs($otherUser)->postJson('/api/v1/interactions/play', ['song_id' => $song2->id]);

        // User 1 should only see song1
        $response = $this->actingAs($this->user)->getJson('/api/v1/songs/recently-played');
        $response->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.id', $song1->id);

        // User 2 should only see song2
        $response = $this->actingAs($otherUser)->getJson('/api/v1/songs/recently-played');
        $response->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.id', $song2->id);
    }

    public function test_recently_played_includes_song_relations(): void
    {
        $artist = Artist::factory()->create(['name' => 'Test Artist']);
        $album = Album::factory()->create(['artist_id' => $artist->id, 'name' => 'Test Album']);
        $song = Song::factory()->create([
            'artist_id' => $artist->id,
            'album_id' => $album->id,
            'artist_name' => 'Test Artist',
            'album_name' => 'Test Album',
        ]);

        $this->actingAs($this->user)->postJson('/api/v1/interactions/play', ['song_id' => $song->id]);

        $response = $this->actingAs($this->user)->getJson('/api/v1/songs/recently-played');

        $response->assertOk()
            ->assertJsonPath('data.0.artist_name', 'Test Artist')
            ->assertJsonPath('data.0.album_name', 'Test Album');
    }
}

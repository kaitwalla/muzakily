<?php

declare(strict_types=1);

namespace Tests\Unit\Actions\Playlists;

use App\Actions\Playlists\CreatePlaylist;
use App\Models\Playlist;
use App\Models\Song;
use App\Models\User;
use App\Services\Playlist\PlaylistCoverService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Mockery;
use Mockery\MockInterface;
use Tests\TestCase;

class CreatePlaylistTest extends TestCase
{
    use RefreshDatabase;

    private CreatePlaylist $action;
    private User $user;
    private PlaylistCoverService&MockInterface $coverServiceMock;

    protected function setUp(): void
    {
        parent::setUp();

        $this->coverServiceMock = Mockery::mock(PlaylistCoverService::class);
        $this->coverServiceMock->shouldReceive('fetchAndStore')->andReturn(false)->byDefault();

        $this->action = new CreatePlaylist($this->coverServiceMock);
        $this->user = User::factory()->create();
    }

    public function test_creates_playlist_with_name(): void
    {
        $playlist = $this->action->execute($this->user, ['name' => 'My Playlist']);

        $this->assertInstanceOf(Playlist::class, $playlist);
        $this->assertEquals('My Playlist', $playlist->name);
        $this->assertEquals($this->user->id, $playlist->user_id);
        $this->assertDatabaseHas('playlists', ['name' => 'My Playlist']);
    }

    public function test_creates_playlist_with_description(): void
    {
        $playlist = $this->action->execute($this->user, [
            'name' => 'My Playlist',
            'description' => 'A great playlist',
        ]);

        $this->assertEquals('A great playlist', $playlist->description);
    }

    public function test_creates_smart_playlist(): void
    {
        $playlist = $this->action->execute($this->user, [
            'name' => 'Smart Playlist',
            'is_smart' => true,
            'rules' => [['id' => 1, 'logic' => 'and', 'rules' => []]],
        ]);

        $this->assertTrue($playlist->is_smart);
        $this->assertNotNull($playlist->rules);
    }

    public function test_creates_playlist_with_songs(): void
    {
        $songs = Song::factory()->count(3)->create();

        $playlist = $this->action->execute($this->user, [
            'name' => 'With Songs',
            'song_ids' => $songs->pluck('id')->toArray(),
        ]);

        $this->assertEquals(3, $playlist->songs()->count());
    }

    public function test_adds_songs_in_order(): void
    {
        $songs = Song::factory()->count(3)->create();

        $playlist = $this->action->execute($this->user, [
            'name' => 'Ordered',
            'song_ids' => $songs->pluck('id')->toArray(),
        ]);

        $playlistSongs = $playlist->songs()->orderByPivot('position')->get();
        $this->assertEquals($songs[0]->id, $playlistSongs[0]->id);
        $this->assertEquals($songs[1]->id, $playlistSongs[1]->id);
        $this->assertEquals($songs[2]->id, $playlistSongs[2]->id);
    }

    public function test_ignores_nonexistent_song_ids(): void
    {
        $song = Song::factory()->create();
        $nonexistentUuid = '00000000-0000-0000-0000-000000000000';

        $playlist = $this->action->execute($this->user, [
            'name' => 'Mixed',
            'song_ids' => [$song->id, $nonexistentUuid],
        ]);

        $this->assertEquals(1, $playlist->songs()->count());
    }

    public function test_creates_playlist_without_songs(): void
    {
        $playlist = $this->action->execute($this->user, ['name' => 'Empty']);

        $this->assertEquals(0, $playlist->songs()->count());
    }

    public function test_fetches_cover_for_smart_playlist(): void
    {
        $this->coverServiceMock
            ->shouldReceive('fetchAndStore')
            ->once()
            ->with(Mockery::type(Playlist::class))
            ->andReturn(true);

        $playlist = $this->action->execute($this->user, [
            'name' => 'Smart Playlist',
            'is_smart' => true,
            'rules' => [['id' => 1, 'logic' => 'and', 'rules' => []]],
        ]);

        $this->assertTrue($playlist->is_smart);
    }

    public function test_does_not_fetch_cover_for_regular_playlist(): void
    {
        $this->coverServiceMock
            ->shouldNotReceive('fetchAndStore');

        $playlist = $this->action->execute($this->user, [
            'name' => 'Regular Playlist',
            'is_smart' => false,
        ]);

        $this->assertFalse($playlist->is_smart);
    }
}

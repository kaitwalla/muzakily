<?php

declare(strict_types=1);

namespace Tests\Unit\Actions\Playlists;

use App\Actions\Playlists\ReorderPlaylistSongs;
use App\Exceptions\SmartPlaylistModificationException;
use App\Models\Playlist;
use App\Models\Song;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ReorderPlaylistSongsTest extends TestCase
{
    use RefreshDatabase;

    private ReorderPlaylistSongs $action;
    private User $user;

    protected function setUp(): void
    {
        parent::setUp();
        $this->action = new ReorderPlaylistSongs();
        $this->user = User::factory()->create();
    }

    public function test_reorders_songs_in_playlist(): void
    {
        $playlist = Playlist::factory()->for($this->user)->create(['is_smart' => false]);
        $songs = Song::factory()->count(3)->create();
        foreach ($songs as $position => $song) {
            $playlist->addSong($song, $position, $this->user);
        }

        // Reverse the order
        $newOrder = $songs->reverse()->pluck('id')->toArray();
        $result = $this->action->execute($playlist, $newOrder);

        $playlistSongs = $result->songs()->orderByPivot('position')->get();
        $this->assertEquals($songs[2]->id, $playlistSongs[0]->id);
        $this->assertEquals($songs[1]->id, $playlistSongs[1]->id);
        $this->assertEquals($songs[0]->id, $playlistSongs[2]->id);
    }

    public function test_throws_exception_for_smart_playlist(): void
    {
        $playlist = Playlist::factory()->for($this->user)->create(['is_smart' => true]);
        $song = Song::factory()->create();

        $this->expectException(SmartPlaylistModificationException::class);
        $this->expectExceptionMessage('Cannot reorder songs in a smart playlist');

        $this->action->execute($playlist, [$song->id]);
    }

    public function test_updates_positions_correctly(): void
    {
        $playlist = Playlist::factory()->for($this->user)->create(['is_smart' => false]);
        $songs = Song::factory()->count(3)->create();
        foreach ($songs as $position => $song) {
            $playlist->addSong($song, $position, $this->user);
        }

        // Move first song to last position
        $newOrder = [$songs[1]->id, $songs[2]->id, $songs[0]->id];
        $this->action->execute($playlist, $newOrder);

        $positions = $playlist->fresh()->songs()
            ->orderByPivot('position')
            ->pluck('songs.id', 'playlist_song.position')
            ->toArray();

        $this->assertEquals($songs[1]->id, $positions[0]);
        $this->assertEquals($songs[2]->id, $positions[1]);
        $this->assertEquals($songs[0]->id, $positions[2]);
    }

    public function test_returns_refreshed_playlist(): void
    {
        $playlist = Playlist::factory()->for($this->user)->create(['is_smart' => false]);
        $song = Song::factory()->create();
        $playlist->addSong($song, 0, $this->user);

        $result = $this->action->execute($playlist, [$song->id]);

        // Verify the same playlist instance is returned (refreshed in-place)
        $this->assertEquals(1, $result->songs()->count());
        $this->assertSame($playlist, $result);
    }
}

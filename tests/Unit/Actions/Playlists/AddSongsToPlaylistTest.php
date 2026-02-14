<?php

declare(strict_types=1);

namespace Tests\Unit\Actions\Playlists;

use App\Actions\Playlists\AddSongsToPlaylist;
use App\Exceptions\SmartPlaylistModificationException;
use App\Models\Playlist;
use App\Models\Song;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AddSongsToPlaylistTest extends TestCase
{
    use RefreshDatabase;

    private AddSongsToPlaylist $action;
    private User $user;

    protected function setUp(): void
    {
        parent::setUp();
        $this->action = new AddSongsToPlaylist();
        $this->user = User::factory()->create();
    }

    public function test_adds_songs_to_playlist(): void
    {
        $playlist = Playlist::factory()->for($this->user)->create(['is_smart' => false]);
        $songs = Song::factory()->count(3)->create();

        $result = $this->action->execute(
            $playlist,
            $songs->pluck('id')->toArray(),
            null,
            $this->user
        );

        $this->assertEquals(3, $result->songs()->count());
    }

    public function test_adds_songs_at_specific_position(): void
    {
        $playlist = Playlist::factory()->for($this->user)->create(['is_smart' => false]);
        $existingSong = Song::factory()->create();
        $playlist->addSong($existingSong, 10, $this->user);

        $newSongs = Song::factory()->count(2)->create();

        $this->action->execute(
            $playlist,
            $newSongs->pluck('id')->toArray(),
            0,
            $this->user
        );

        $playlistSongs = $playlist->fresh()->songs()->orderByPivot('position')->get();
        // New songs at position 0, 1 should come before existing song at 10
        $this->assertEquals($newSongs[0]->id, $playlistSongs[0]->id);
        $this->assertEquals($newSongs[1]->id, $playlistSongs[1]->id);
        $this->assertEquals($existingSong->id, $playlistSongs[2]->id);
    }

    public function test_increments_position_for_multiple_songs(): void
    {
        $playlist = Playlist::factory()->for($this->user)->create(['is_smart' => false]);
        $songs = Song::factory()->count(3)->create();

        $this->action->execute($playlist, $songs->pluck('id')->toArray(), 5, $this->user);

        $positions = $playlist->fresh()->songs()
            ->orderByPivot('position')
            ->pluck('playlist_song.position')
            ->toArray();
        $this->assertEquals([5, 6, 7], $positions);
    }

    public function test_throws_exception_for_smart_playlist(): void
    {
        $playlist = Playlist::factory()->for($this->user)->create(['is_smart' => true]);
        $song = Song::factory()->create();

        $this->expectException(SmartPlaylistModificationException::class);
        $this->expectExceptionMessage('Cannot add songs to a smart playlist');

        $this->action->execute($playlist, [$song->id], null, $this->user);
    }

    public function test_ignores_nonexistent_song_ids(): void
    {
        $playlist = Playlist::factory()->for($this->user)->create(['is_smart' => false]);
        $song = Song::factory()->create();
        $nonexistentUuid = '00000000-0000-0000-0000-000000000000';

        $result = $this->action->execute(
            $playlist,
            [$song->id, $nonexistentUuid],
            null,
            $this->user
        );

        $this->assertEquals(1, $result->songs()->count());
    }

    public function test_returns_refreshed_playlist(): void
    {
        $playlist = Playlist::factory()->for($this->user)->create(['is_smart' => false]);
        $song = Song::factory()->create();

        $result = $this->action->execute($playlist, [$song->id], null, $this->user);

        // Verify the playlist has the new song
        $this->assertEquals(1, $result->songs()->count());
        $this->assertSame($playlist, $result);
    }
}

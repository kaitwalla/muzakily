<?php

declare(strict_types=1);

namespace Tests\Unit\Actions\Playlists;

use App\Actions\Playlists\RemoveSongsFromPlaylist;
use App\Exceptions\SmartPlaylistModificationException;
use App\Models\Playlist;
use App\Models\Song;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class RemoveSongsFromPlaylistTest extends TestCase
{
    use RefreshDatabase;

    private RemoveSongsFromPlaylist $action;
    private User $user;

    protected function setUp(): void
    {
        parent::setUp();
        $this->action = new RemoveSongsFromPlaylist();
        $this->user = User::factory()->create();
    }

    public function test_removes_songs_from_playlist(): void
    {
        $playlist = Playlist::factory()->for($this->user)->create(['is_smart' => false]);
        $songs = Song::factory()->count(3)->create();
        foreach ($songs as $position => $song) {
            $playlist->addSong($song, $position, $this->user);
        }

        $result = $this->action->execute($playlist, [$songs[0]->id, $songs[1]->id]);

        $this->assertEquals(1, $result->songs()->count());
        $this->assertEquals($songs[2]->id, $result->songs()->first()->id);
    }

    public function test_throws_exception_for_smart_playlist(): void
    {
        $playlist = Playlist::factory()->for($this->user)->create(['is_smart' => true]);
        $song = Song::factory()->create();

        $this->expectException(SmartPlaylistModificationException::class);
        $this->expectExceptionMessage('Cannot remove songs from a smart playlist');

        $this->action->execute($playlist, [$song->id]);
    }

    public function test_ignores_nonexistent_song_ids(): void
    {
        $playlist = Playlist::factory()->for($this->user)->create(['is_smart' => false]);
        $song = Song::factory()->create();
        $playlist->addSong($song, 0, $this->user);
        $nonexistentUuid = '00000000-0000-0000-0000-000000000000';

        // Should not throw, just ignore the nonexistent id
        $result = $this->action->execute($playlist, [$nonexistentUuid]);

        $this->assertEquals(1, $result->songs()->count());
    }

    public function test_removes_all_specified_songs(): void
    {
        $playlist = Playlist::factory()->for($this->user)->create(['is_smart' => false]);
        $songs = Song::factory()->count(2)->create();
        foreach ($songs as $position => $song) {
            $playlist->addSong($song, $position, $this->user);
        }

        $result = $this->action->execute($playlist, $songs->pluck('id')->toArray());

        $this->assertEquals(0, $result->songs()->count());
    }

    public function test_returns_refreshed_playlist(): void
    {
        $playlist = Playlist::factory()->for($this->user)->create(['is_smart' => false]);
        $song = Song::factory()->create();
        $playlist->addSong($song, 0, $this->user);

        $result = $this->action->execute($playlist, [$song->id]);

        // Verify the playlist is refreshed with updated data
        $this->assertEquals(0, $result->songs()->count());
        $this->assertSame($playlist, $result);
    }
}

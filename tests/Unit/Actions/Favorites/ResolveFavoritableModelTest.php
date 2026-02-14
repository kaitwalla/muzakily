<?php

declare(strict_types=1);

namespace Tests\Unit\Actions\Favorites;

use App\Actions\Favorites\ResolveFavoritableModel;
use App\Exceptions\ResourceNotFoundException;
use App\Models\Album;
use App\Models\Artist;
use App\Models\Playlist;
use App\Models\Song;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ResolveFavoritableModelTest extends TestCase
{
    use RefreshDatabase;

    private ResolveFavoritableModel $action;

    protected function setUp(): void
    {
        parent::setUp();
        $this->action = new ResolveFavoritableModel();
    }

    public function test_resolves_song_by_id(): void
    {
        $song = Song::factory()->create();

        $result = $this->action->execute('song', $song->id);

        $this->assertInstanceOf(Song::class, $result);
        $this->assertEquals($song->id, $result->id);
    }

    public function test_resolves_album_by_uuid(): void
    {
        $album = Album::factory()->create();

        $result = $this->action->execute('album', $album->uuid);

        $this->assertInstanceOf(Album::class, $result);
        $this->assertEquals($album->id, $result->id);
    }

    public function test_resolves_artist_by_uuid(): void
    {
        $artist = Artist::factory()->create();

        $result = $this->action->execute('artist', $artist->uuid);

        $this->assertInstanceOf(Artist::class, $result);
        $this->assertEquals($artist->id, $result->id);
    }

    public function test_resolves_playlist_by_id(): void
    {
        $user = User::factory()->create();
        $playlist = Playlist::factory()->for($user)->create();

        $result = $this->action->execute('playlist', $playlist->id);

        $this->assertInstanceOf(Playlist::class, $result);
        $this->assertEquals($playlist->id, $result->id);
    }

    public function test_throws_exception_for_nonexistent_song(): void
    {
        $this->expectException(ResourceNotFoundException::class);
        $this->expectExceptionMessage('Resource not found');

        $this->action->execute('song', '00000000-0000-0000-0000-000000000000');
    }

    public function test_throws_exception_for_nonexistent_album(): void
    {
        $this->expectException(ResourceNotFoundException::class);

        $this->action->execute('album', '00000000-0000-0000-0000-000000000000');
    }

    public function test_throws_exception_for_nonexistent_artist(): void
    {
        $this->expectException(ResourceNotFoundException::class);

        $this->action->execute('artist', '00000000-0000-0000-0000-000000000000');
    }

    public function test_throws_exception_for_nonexistent_playlist(): void
    {
        $this->expectException(ResourceNotFoundException::class);

        $this->action->execute('playlist', '00000000-0000-0000-0000-000000000000');
    }

    public function test_throws_exception_for_invalid_type(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Invalid favoriteable type: invalid');

        $this->action->execute('invalid', 'some-id');
    }
}

<?php

declare(strict_types=1);

namespace Tests\Unit\Actions\Favorites;

use App\Actions\Favorites\AddFavorite;
use App\Models\Album;
use App\Models\Artist;
use App\Models\Favorite;
use App\Models\Playlist;
use App\Models\Song;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AddFavoriteTest extends TestCase
{
    use RefreshDatabase;

    private AddFavorite $action;
    private User $user;

    protected function setUp(): void
    {
        parent::setUp();
        $this->action = new AddFavorite();
        $this->user = User::factory()->create();
    }

    public function test_adds_song_to_favorites(): void
    {
        $song = Song::factory()->create();

        $this->action->execute($this->user, $song);

        $this->assertDatabaseHas('favorites', [
            'user_id' => $this->user->id,
            'favoritable_type' => Song::class,
            'favoritable_id' => $song->id,
        ]);
    }

    public function test_adds_album_to_favorites(): void
    {
        $album = Album::factory()->create();

        $this->action->execute($this->user, $album);

        $this->assertDatabaseHas('favorites', [
            'user_id' => $this->user->id,
            'favoritable_type' => Album::class,
            'favoritable_id' => $album->id,
        ]);
    }

    public function test_adds_artist_to_favorites(): void
    {
        $artist = Artist::factory()->create();

        $this->action->execute($this->user, $artist);

        $this->assertDatabaseHas('favorites', [
            'user_id' => $this->user->id,
            'favoritable_type' => Artist::class,
            'favoritable_id' => $artist->id,
        ]);
    }

    public function test_adds_playlist_to_favorites(): void
    {
        $playlist = Playlist::factory()->for($this->user)->create();

        $this->action->execute($this->user, $playlist);

        $this->assertDatabaseHas('favorites', [
            'user_id' => $this->user->id,
            'favoritable_type' => Playlist::class,
            'favoritable_id' => $playlist->id,
        ]);
    }

    public function test_does_not_duplicate_favorites(): void
    {
        $song = Song::factory()->create();

        $this->action->execute($this->user, $song);
        $this->action->execute($this->user, $song);

        $count = Favorite::where('user_id', $this->user->id)
            ->where('favoritable_type', Song::class)
            ->where('favoritable_id', $song->id)
            ->count();

        $this->assertEquals(1, $count);
    }
}

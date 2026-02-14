<?php

declare(strict_types=1);

namespace Tests\Unit\Actions\Favorites;

use App\Actions\Favorites\RemoveFavorite;
use App\Models\Album;
use App\Models\Artist;
use App\Models\Favorite;
use App\Models\Playlist;
use App\Models\Song;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class RemoveFavoriteTest extends TestCase
{
    use RefreshDatabase;

    private RemoveFavorite $action;
    private User $user;

    protected function setUp(): void
    {
        parent::setUp();
        $this->action = new RemoveFavorite();
        $this->user = User::factory()->create();
    }

    public function test_removes_song_from_favorites(): void
    {
        $song = Song::factory()->create();
        Favorite::add($this->user, $song);

        $this->action->execute($this->user, $song);

        $this->assertDatabaseMissing('favorites', [
            'user_id' => $this->user->id,
            'favoritable_type' => Song::class,
            'favoritable_id' => $song->id,
        ]);
    }

    public function test_removes_album_from_favorites(): void
    {
        $album = Album::factory()->create();
        Favorite::add($this->user, $album);

        $this->action->execute($this->user, $album);

        $this->assertDatabaseMissing('favorites', [
            'user_id' => $this->user->id,
            'favoritable_type' => Album::class,
            'favoritable_id' => $album->id,
        ]);
    }

    public function test_removes_artist_from_favorites(): void
    {
        $artist = Artist::factory()->create();
        Favorite::add($this->user, $artist);

        $this->action->execute($this->user, $artist);

        $this->assertDatabaseMissing('favorites', [
            'user_id' => $this->user->id,
            'favoritable_type' => Artist::class,
            'favoritable_id' => $artist->id,
        ]);
    }

    public function test_removes_playlist_from_favorites(): void
    {
        $playlist = Playlist::factory()->for($this->user)->create();
        Favorite::add($this->user, $playlist);

        $this->action->execute($this->user, $playlist);

        $this->assertDatabaseMissing('favorites', [
            'user_id' => $this->user->id,
            'favoritable_type' => Playlist::class,
            'favoritable_id' => $playlist->id,
        ]);
    }

    public function test_does_not_error_when_favorite_does_not_exist(): void
    {
        $song = Song::factory()->create();

        // Should not throw - verify no favorites exist before or after
        $this->action->execute($this->user, $song);

        $this->assertDatabaseMissing('favorites', [
            'user_id' => $this->user->id,
            'favoritable_type' => Song::class,
            'favoritable_id' => $song->id,
        ]);
    }
}

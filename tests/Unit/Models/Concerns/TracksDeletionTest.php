<?php

declare(strict_types=1);

namespace Tests\Unit\Models\Concerns;

use App\Models\Album;
use App\Models\Artist;
use App\Models\DeletedItem;
use App\Models\Playlist;
use App\Models\Song;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class TracksDeletionTest extends TestCase
{
    use RefreshDatabase;

    public function test_song_deletion_is_tracked(): void
    {
        $song = Song::factory()->create();
        $songId = $song->id;

        $song->delete();

        $this->assertDatabaseHas('deleted_items', [
            'deletable_type' => 'song',
            'deletable_id' => $songId,
            'user_id' => null,
        ]);
    }

    public function test_album_deletion_is_tracked(): void
    {
        $album = Album::factory()->create();
        $albumUuid = $album->uuid;

        $album->delete();

        $this->assertDatabaseHas('deleted_items', [
            'deletable_type' => 'album',
            'deletable_id' => $albumUuid,
            'user_id' => null,
        ]);
    }

    public function test_artist_deletion_is_tracked(): void
    {
        $artist = Artist::factory()->create();
        $artistUuid = $artist->uuid;

        $artist->delete();

        $this->assertDatabaseHas('deleted_items', [
            'deletable_type' => 'artist',
            'deletable_id' => $artistUuid,
            'user_id' => null,
        ]);
    }

    public function test_playlist_deletion_is_tracked_with_user_id(): void
    {
        $user = User::factory()->create();
        $playlist = Playlist::factory()->create(['user_id' => $user->id]);
        $playlistId = $playlist->id;

        $playlist->delete();

        $this->assertDatabaseHas('deleted_items', [
            'deletable_type' => 'playlist',
            'deletable_id' => $playlistId,
            'user_id' => $user->id,
        ]);
    }

    public function test_soft_deleted_song_can_be_queried_in_deleted_items(): void
    {
        $song = Song::factory()->create();
        $songId = $song->id;

        // Soft delete the song
        $song->delete();

        // Verify the deletion is tracked
        $this->assertDatabaseHas('deleted_items', [
            'deletable_type' => 'song',
            'deletable_id' => $songId,
        ]);

        // The song should be soft deleted (not accessible in normal queries)
        $this->assertSoftDeleted('songs', ['id' => $songId]);

        // But we have a record in deleted_items for sync purposes
        $deletedItem = DeletedItem::where('deletable_id', $songId)->first();
        $this->assertNotNull($deletedItem);
        $this->assertEquals('song', $deletedItem->deletable_type);
    }

    public function test_multiple_deletions_create_multiple_records(): void
    {
        $song1 = Song::factory()->create();
        $song2 = Song::factory()->create();

        $song1->delete();
        $song2->delete();

        $this->assertCount(2, DeletedItem::ofType('song')->get());
    }
}

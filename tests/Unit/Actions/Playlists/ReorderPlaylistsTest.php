<?php

declare(strict_types=1);

namespace Tests\Unit\Actions\Playlists;

use App\Actions\Playlists\ReorderPlaylists;
use App\Models\Playlist;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ReorderPlaylistsTest extends TestCase
{
    use RefreshDatabase;

    private ReorderPlaylists $action;
    private User $user;

    protected function setUp(): void
    {
        parent::setUp();
        $this->action = new ReorderPlaylists();
        $this->user = User::factory()->create();
    }

    public function test_reorders_playlists_for_user(): void
    {
        $playlist1 = Playlist::factory()->for($this->user)->create(['position' => 0]);
        $playlist2 = Playlist::factory()->for($this->user)->create(['position' => 1]);
        $playlist3 = Playlist::factory()->for($this->user)->create(['position' => 2]);

        // Reverse the order
        $newOrder = [$playlist3->id, $playlist2->id, $playlist1->id];
        $this->action->execute($this->user, $newOrder);

        $this->assertEquals(0, $playlist3->fresh()->position);
        $this->assertEquals(1, $playlist2->fresh()->position);
        $this->assertEquals(2, $playlist1->fresh()->position);
    }

    public function test_only_reorders_playlists_in_provided_array(): void
    {
        $playlist1 = Playlist::factory()->for($this->user)->create(['position' => 0]);
        $playlist2 = Playlist::factory()->for($this->user)->create(['position' => 1]);
        $playlist3 = Playlist::factory()->for($this->user)->create(['position' => 2]);

        // Only reorder first two, leave third unchanged
        $newOrder = [$playlist2->id, $playlist1->id];
        $this->action->execute($this->user, $newOrder);

        $this->assertEquals(0, $playlist2->fresh()->position);
        $this->assertEquals(1, $playlist1->fresh()->position);
        $this->assertEquals(2, $playlist3->fresh()->position);
    }

    public function test_ignores_playlists_not_owned_by_user(): void
    {
        $otherUser = User::factory()->create();
        $myPlaylist = Playlist::factory()->for($this->user)->create(['position' => 0]);
        $otherPlaylist = Playlist::factory()->for($otherUser)->create(['position' => 0]);

        // Try to reorder with another user's playlist
        $newOrder = [$otherPlaylist->id, $myPlaylist->id];
        $this->action->execute($this->user, $newOrder);

        // My playlist should be at position 0 (the only one that matches)
        $this->assertEquals(0, $myPlaylist->fresh()->position);
        // Other user's playlist should remain unchanged
        $this->assertEquals(0, $otherPlaylist->fresh()->position);
    }

    public function test_ignores_nonexistent_playlist_ids(): void
    {
        $playlist1 = Playlist::factory()->for($this->user)->create(['position' => 0]);
        $playlist2 = Playlist::factory()->for($this->user)->create(['position' => 1]);

        $fakeId = '00000000-0000-0000-0000-000000000000';
        $newOrder = [$playlist2->id, $fakeId, $playlist1->id];
        $this->action->execute($this->user, $newOrder);

        $this->assertEquals(0, $playlist2->fresh()->position);
        $this->assertEquals(1, $playlist1->fresh()->position);
    }

    public function test_handles_empty_array(): void
    {
        $playlist = Playlist::factory()->for($this->user)->create(['position' => 0]);

        $this->action->execute($this->user, []);

        $this->assertEquals(0, $playlist->fresh()->position);
    }

    public function test_handles_single_playlist(): void
    {
        $playlist = Playlist::factory()->for($this->user)->create(['position' => 5]);

        $this->action->execute($this->user, [$playlist->id]);

        $this->assertEquals(0, $playlist->fresh()->position);
    }
}

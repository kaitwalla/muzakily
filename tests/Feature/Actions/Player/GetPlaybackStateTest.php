<?php

declare(strict_types=1);

namespace Tests\Feature\Actions\Player;

use App\Actions\Player\GetPlaybackState;
use App\Models\PlayerDevice;
use App\Models\Song;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class GetPlaybackStateTest extends TestCase
{
    use RefreshDatabase;

    private GetPlaybackState $action;
    private User $user;

    protected function setUp(): void
    {
        parent::setUp();
        $this->action = new GetPlaybackState();
        $this->user = User::factory()->create();
    }

    public function test_returns_empty_state_when_no_device_exists(): void
    {
        $result = $this->action->execute($this->user);

        $this->assertNull($result['active_device']);
        $this->assertFalse($result['is_playing']);
        $this->assertNull($result['current_song']);
        $this->assertEquals(0, $result['position']);
        $this->assertEquals(1, $result['volume']);
        $this->assertCount(0, $result['queue']);
    }

    public function test_returns_most_recent_device(): void
    {
        $oldDevice = PlayerDevice::factory()->for($this->user)->create([
            'name' => 'Old Device',
            'last_seen' => now()->subMinutes(10),
        ]);
        $newDevice = PlayerDevice::factory()->for($this->user)->create([
            'name' => 'New Device',
            'last_seen' => now(),
        ]);

        $result = $this->action->execute($this->user);

        $this->assertEquals($newDevice->id, $result['active_device']['device_id']);
        $this->assertEquals('New Device', $result['active_device']['name']);
    }

    public function test_returns_playing_state(): void
    {
        PlayerDevice::factory()->for($this->user)->create([
            'state' => ['is_playing' => true],
        ]);

        $result = $this->action->execute($this->user);

        $this->assertTrue($result['is_playing']);
    }

    public function test_returns_current_song(): void
    {
        $song = Song::factory()->create();
        PlayerDevice::factory()->for($this->user)->create([
            'state' => ['song_id' => $song->id],
        ]);

        $result = $this->action->execute($this->user);

        $this->assertNotNull($result['current_song']);
        $this->assertEquals($song->id, $result['current_song']->id);
    }

    public function test_returns_position_and_volume(): void
    {
        PlayerDevice::factory()->for($this->user)->create([
            'state' => [
                'position' => 42.5,
                'volume' => 0.8,
            ],
        ]);

        $result = $this->action->execute($this->user);

        $this->assertEquals(42.5, $result['position']);
        $this->assertEquals(0.8, $result['volume']);
    }

    public function test_returns_all_songs_in_queue(): void
    {
        $songs = Song::factory()->count(3)->create();
        $queueIds = $songs->pluck('id')->toArray();

        PlayerDevice::factory()->for($this->user)->create([
            'state' => ['queue' => $queueIds],
        ]);

        $result = $this->action->execute($this->user);

        $this->assertCount(3, $result['queue']);
    }

    public function test_handles_missing_songs_in_queue(): void
    {
        $song = Song::factory()->create();
        $nonexistentId = '00000000-0000-0000-0000-000000000000';

        PlayerDevice::factory()->for($this->user)->create([
            'state' => ['queue' => [$song->id, $nonexistentId]],
        ]);

        $result = $this->action->execute($this->user);

        $this->assertCount(1, $result['queue']);
    }

    public function test_preserves_queue_order(): void
    {
        $songs = Song::factory()->count(3)->create();
        // Reverse the order
        $queueIds = $songs->pluck('id')->reverse()->values()->toArray();

        PlayerDevice::factory()->for($this->user)->create([
            'state' => ['queue' => $queueIds],
        ]);

        $result = $this->action->execute($this->user);

        $resultIds = collect($result['queue'])->pluck('id')->toArray();
        $this->assertEquals($queueIds, $resultIds);
    }

    public function test_does_not_return_other_users_devices(): void
    {
        $otherUser = User::factory()->create();
        PlayerDevice::factory()->for($otherUser)->create([
            'name' => 'Other User Device',
            'last_seen' => now(),
        ]);

        $result = $this->action->execute($this->user);

        $this->assertNull($result['active_device']);
    }
}

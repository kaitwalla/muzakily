<?php

declare(strict_types=1);

namespace Tests\Unit\Models;

use App\Models\PlayerDevice;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PlayerDeviceTest extends TestCase
{
    use RefreshDatabase;

    private User $user;

    protected function setUp(): void
    {
        parent::setUp();
        $this->user = User::factory()->create();
    }

    public function test_creates_player_device(): void
    {
        $device = PlayerDevice::create([
            'id' => 'device_abc123',
            'user_id' => $this->user->id,
            'name' => 'Living Room Speaker',
            'type' => 'web',
        ]);

        $this->assertDatabaseHas('player_devices', [
            'id' => 'device_abc123',
            'user_id' => $this->user->id,
            'name' => 'Living Room Speaker',
            'type' => 'web',
        ]);
    }

    public function test_belongs_to_user(): void
    {
        $device = PlayerDevice::factory()->create(['user_id' => $this->user->id]);

        $this->assertInstanceOf(User::class, $device->user);
        $this->assertEquals($this->user->id, $device->user->id);
    }

    public function test_is_playing_returns_false_when_no_state(): void
    {
        $device = PlayerDevice::factory()->create(['state' => null]);

        $this->assertFalse($device->is_playing);
    }

    public function test_is_playing_returns_true_when_playing(): void
    {
        $device = PlayerDevice::factory()->create([
            'state' => ['is_playing' => true],
        ]);

        $this->assertTrue($device->is_playing);
    }

    public function test_current_song_id_returns_null_when_no_state(): void
    {
        $device = PlayerDevice::factory()->create(['state' => null]);

        $this->assertNull($device->current_song_id);
    }

    public function test_current_song_id_returns_id_from_state(): void
    {
        $device = PlayerDevice::factory()->create([
            'state' => ['song_id' => 'test-song-id'],
        ]);

        $this->assertEquals('test-song-id', $device->current_song_id);
    }

    public function test_position_returns_null_when_no_state(): void
    {
        $device = PlayerDevice::factory()->create(['state' => null]);

        $this->assertNull($device->position);
    }

    public function test_position_returns_float_from_state(): void
    {
        $device = PlayerDevice::factory()->create([
            'state' => ['position' => 45.5],
        ]);

        $this->assertEquals(45.5, $device->position);
    }

    public function test_volume_returns_null_when_no_state(): void
    {
        $device = PlayerDevice::factory()->create(['state' => null]);

        $this->assertNull($device->volume);
    }

    public function test_volume_returns_float_from_state(): void
    {
        $device = PlayerDevice::factory()->create([
            'state' => ['volume' => 0.8],
        ]);

        $this->assertEquals(0.8, $device->volume);
    }

    public function test_update_state_merges_with_existing(): void
    {
        $device = PlayerDevice::factory()->create([
            'state' => ['is_playing' => true, 'position' => 10.0],
        ]);

        $device->updateState(['position' => 20.0]);

        $device->refresh();
        $this->assertTrue($device->is_playing);
        $this->assertEquals(20.0, $device->position);
    }

    public function test_update_state_creates_state_when_null(): void
    {
        $device = PlayerDevice::factory()->create(['state' => null]);

        $device->updateState(['is_playing' => true]);

        $device->refresh();
        $this->assertTrue($device->is_playing);
    }

    public function test_update_state_updates_last_seen(): void
    {
        $oldLastSeen = now()->subHour();
        $device = PlayerDevice::factory()->create(['last_seen' => $oldLastSeen]);

        $device->updateState(['is_playing' => true]);

        $device->refresh();
        $this->assertTrue($device->last_seen->gt($oldLastSeen));
    }

    public function test_is_online_returns_true_for_recent_device(): void
    {
        $device = PlayerDevice::factory()->create(['last_seen' => now()]);

        $this->assertTrue($device->isOnline());
    }

    public function test_is_online_returns_false_for_stale_device(): void
    {
        $device = PlayerDevice::factory()->create(['last_seen' => now()->subMinutes(5)]);

        $this->assertFalse($device->isOnline(60));
    }

    public function test_online_for_user_returns_only_online_devices(): void
    {
        $onlineDevice = PlayerDevice::factory()->create([
            'user_id' => $this->user->id,
            'last_seen' => now(),
        ]);
        $offlineDevice = PlayerDevice::factory()->create([
            'user_id' => $this->user->id,
            'last_seen' => now()->subHours(2),
        ]);

        $onlineDevices = PlayerDevice::onlineForUser($this->user);

        $this->assertCount(1, $onlineDevices);
        $this->assertEquals($onlineDevice->id, $onlineDevices->first()->id);
    }

    public function test_cleanup_stale_removes_old_devices(): void
    {
        PlayerDevice::factory()->create([
            'user_id' => $this->user->id,
            'last_seen' => now()->subDays(2),
        ]);
        $recentDevice = PlayerDevice::factory()->create([
            'user_id' => $this->user->id,
            'last_seen' => now(),
        ]);

        $deleted = PlayerDevice::cleanupStale(24);

        $this->assertEquals(1, $deleted);
        $this->assertDatabaseHas('player_devices', ['id' => $recentDevice->id]);
    }

    public function test_touch_updates_last_seen(): void
    {
        $oldLastSeen = now()->subHour();
        $device = PlayerDevice::factory()->create(['last_seen' => $oldLastSeen]);

        $device->touch();

        $device->refresh();
        $this->assertTrue($device->last_seen->gt($oldLastSeen));
    }

    public function test_created_at_auto_set(): void
    {
        $device = PlayerDevice::create([
            'id' => 'device_test',
            'user_id' => $this->user->id,
            'name' => 'Test Device',
            'type' => 'web',
        ]);

        $this->assertNotNull($device->created_at);
    }

    public function test_last_seen_auto_set(): void
    {
        $device = PlayerDevice::create([
            'id' => 'device_test',
            'user_id' => $this->user->id,
            'name' => 'Test Device',
            'type' => 'web',
        ]);

        $this->assertNotNull($device->last_seen);
    }
}

<?php

declare(strict_types=1);

namespace Tests\Feature\Api\V1;

use App\Events\Player\QueueUpdated;
use App\Events\Player\RemoteCommand;
use App\Models\Album;
use App\Models\Artist;
use App\Models\PlayerDevice;
use App\Models\Song;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Event;
use Tests\TestCase;

class RemoteControlEndpointTest extends TestCase
{
    use RefreshDatabase;

    private User $user;
    private PlayerDevice $device;

    protected function setUp(): void
    {
        parent::setUp();
        $this->user = User::factory()->create();
        $this->device = PlayerDevice::factory()->create([
            'user_id' => $this->user->id,
            'id' => 'test_device',
        ]);
        Event::fake();
    }

    public function test_control_requires_authentication(): void
    {
        $response = $this->postJson('/api/v1/player/control', [
            'target_device_id' => $this->device->id,
            'command' => 'play',
        ]);

        $response->assertUnauthorized();
    }

    public function test_control_sends_play_command(): void
    {
        $response = $this->actingAs($this->user)->postJson('/api/v1/player/control', [
            'target_device_id' => $this->device->id,
            'command' => 'play',
        ]);

        $response->assertOk()
            ->assertJsonPath('data.status', 'command_sent')
            ->assertJsonPath('data.target_device_id', $this->device->id)
            ->assertJsonPath('data.command', 'play');

        Event::assertDispatched(RemoteCommand::class, function ($event) {
            return $event->targetDeviceId === $this->device->id
                && $event->command === 'play';
        });
    }

    public function test_control_sends_pause_command(): void
    {
        $response = $this->actingAs($this->user)->postJson('/api/v1/player/control', [
            'target_device_id' => $this->device->id,
            'command' => 'pause',
        ]);

        $response->assertOk();

        Event::assertDispatched(RemoteCommand::class, function ($event) {
            return $event->command === 'pause';
        });
    }

    public function test_control_sends_seek_command_with_payload(): void
    {
        $response = $this->actingAs($this->user)->postJson('/api/v1/player/control', [
            'target_device_id' => $this->device->id,
            'command' => 'seek',
            'payload' => ['position' => 60],
        ]);

        $response->assertOk();

        Event::assertDispatched(RemoteCommand::class, function ($event) {
            return $event->command === 'seek'
                && $event->payload['position'] === 60;
        });
    }

    public function test_control_sends_volume_command(): void
    {
        $response = $this->actingAs($this->user)->postJson('/api/v1/player/control', [
            'target_device_id' => $this->device->id,
            'command' => 'volume',
            'payload' => ['level' => 0.5],
        ]);

        $response->assertOk();

        Event::assertDispatched(RemoteCommand::class, function ($event) {
            return $event->command === 'volume'
                && $event->payload['level'] === 0.5;
        });
    }

    public function test_control_validates_required_fields(): void
    {
        $response = $this->actingAs($this->user)->postJson('/api/v1/player/control', []);

        $response->assertUnprocessable()
            ->assertJsonValidationErrors(['target_device_id', 'command']);
    }

    public function test_control_validates_command_enum(): void
    {
        $response = $this->actingAs($this->user)->postJson('/api/v1/player/control', [
            'target_device_id' => $this->device->id,
            'command' => 'invalid_command',
        ]);

        $response->assertUnprocessable()
            ->assertJsonValidationErrors(['command']);
    }

    public function test_control_returns_404_for_other_users_device(): void
    {
        $otherUser = User::factory()->create();
        $otherDevice = PlayerDevice::factory()->create(['user_id' => $otherUser->id]);

        $response = $this->actingAs($this->user)->postJson('/api/v1/player/control', [
            'target_device_id' => $otherDevice->id,
            'command' => 'play',
        ]);

        $response->assertNotFound();
        Event::assertNotDispatched(RemoteCommand::class);
    }

    public function test_state_requires_authentication(): void
    {
        $response = $this->getJson('/api/v1/player/state');

        $response->assertUnauthorized();
    }

    public function test_state_returns_empty_when_no_devices(): void
    {
        $userWithNoDevices = User::factory()->create();

        $response = $this->actingAs($userWithNoDevices)->getJson('/api/v1/player/state');

        $response->assertOk()
            ->assertJsonPath('data.active_device', null)
            ->assertJsonPath('data.is_playing', false)
            ->assertJsonPath('data.current_song', null)
            ->assertJsonPath('data.position', 0)
            ->assertJsonPath('data.volume', 1)
            ->assertJsonPath('data.queue', []);
    }

    public function test_state_returns_most_recently_active_device(): void
    {
        $oldDevice = PlayerDevice::factory()->create([
            'user_id' => $this->user->id,
            'id' => 'old_device',
            'last_seen' => now()->subMinutes(5),
        ]);
        $newDevice = PlayerDevice::factory()->create([
            'user_id' => $this->user->id,
            'id' => 'new_device',
            'name' => 'Newest Device',
            'last_seen' => now(),
        ]);

        $response = $this->actingAs($this->user)->getJson('/api/v1/player/state');

        $response->assertOk()
            ->assertJsonPath('data.active_device.device_id', 'new_device')
            ->assertJsonPath('data.active_device.name', 'Newest Device');
    }

    public function test_state_includes_current_song(): void
    {
        $artist = Artist::factory()->create();
        $album = Album::factory()->create(['artist_id' => $artist->id]);
        $song = Song::factory()->create([
            'artist_id' => $artist->id,
            'album_id' => $album->id,
            'title' => 'Test Song',
        ]);

        $this->device->update([
            'state' => [
                'is_playing' => true,
                'song_id' => $song->id,
                'position' => 30.5,
                'volume' => 0.75,
            ],
            'last_seen' => now(),
        ]);

        $response = $this->actingAs($this->user)->getJson('/api/v1/player/state');

        $response->assertOk()
            ->assertJsonPath('data.is_playing', true)
            ->assertJsonPath('data.current_song.id', $song->id)
            ->assertJsonPath('data.current_song.title', 'Test Song')
            ->assertJsonPath('data.position', 30.5)
            ->assertJsonPath('data.volume', 0.75);
    }

    public function test_state_includes_queue(): void
    {
        $artist = Artist::factory()->create();
        $album = Album::factory()->create(['artist_id' => $artist->id]);
        $song1 = Song::factory()->create(['artist_id' => $artist->id, 'album_id' => $album->id]);
        $song2 = Song::factory()->create(['artist_id' => $artist->id, 'album_id' => $album->id]);

        $this->device->update([
            'state' => [
                'queue' => [$song1->id, $song2->id],
            ],
            'last_seen' => now(),
        ]);

        $response = $this->actingAs($this->user)->getJson('/api/v1/player/state');

        $response->assertOk()
            ->assertJsonCount(2, 'data.queue');
    }

    public function test_sync_requires_authentication(): void
    {
        $response = $this->postJson('/api/v1/player/sync', [
            'queue' => [],
        ]);

        $response->assertUnauthorized();
    }

    public function test_sync_broadcasts_queue_update(): void
    {
        $artist = Artist::factory()->create();
        $album = Album::factory()->create(['artist_id' => $artist->id]);
        $song = Song::factory()->create(['artist_id' => $artist->id, 'album_id' => $album->id]);

        $response = $this->actingAs($this->user)->postJson('/api/v1/player/sync', [
            'queue' => [$song->id],
            'current_index' => 0,
            'position' => 15.5,
        ]);

        $response->assertOk()
            ->assertJsonPath('data.status', 'synced');

        Event::assertDispatched(QueueUpdated::class, function ($event) use ($song) {
            return in_array($song->id, $event->queue)
                && $event->currentIndex === 0
                && $event->position === 15.5;
        });
    }

    public function test_sync_returns_devices_notified_count(): void
    {
        // Update the setUp device to be online
        $this->device->update(['last_seen' => now()]);

        // Create another online device
        PlayerDevice::factory()->create([
            'user_id' => $this->user->id,
            'last_seen' => now(),
        ]);

        $artist = Artist::factory()->create();
        $album = Album::factory()->create(['artist_id' => $artist->id]);
        $song = Song::factory()->create(['artist_id' => $artist->id, 'album_id' => $album->id]);

        $response = $this->actingAs($this->user)->postJson('/api/v1/player/sync', [
            'queue' => [$song->id],
        ]);

        $response->assertOk()
            ->assertJsonPath('data.devices_notified', 2);
    }

    public function test_sync_validates_queue_is_array(): void
    {
        $response = $this->actingAs($this->user)->postJson('/api/v1/player/sync', [
            'queue' => 'not-an-array',
        ]);

        $response->assertUnprocessable()
            ->assertJsonValidationErrors(['queue']);
    }

    public function test_sync_validates_queue_items_are_uuids(): void
    {
        $response = $this->actingAs($this->user)->postJson('/api/v1/player/sync', [
            'queue' => ['not-a-uuid'],
        ]);

        $response->assertUnprocessable()
            ->assertJsonValidationErrors(['queue.0']);
    }
}

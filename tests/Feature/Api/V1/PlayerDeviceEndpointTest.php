<?php

declare(strict_types=1);

namespace Tests\Feature\Api\V1;

use App\Models\Album;
use App\Models\Artist;
use App\Models\PlayerDevice;
use App\Models\Song;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PlayerDeviceEndpointTest extends TestCase
{
    use RefreshDatabase;

    private User $user;

    protected function setUp(): void
    {
        parent::setUp();
        $this->user = User::factory()->create();
    }

    public function test_list_devices_requires_authentication(): void
    {
        $response = $this->getJson('/api/v1/player/devices');

        $response->assertUnauthorized();
    }

    public function test_list_devices_returns_empty_array_when_no_devices(): void
    {
        $response = $this->actingAs($this->user)->getJson('/api/v1/player/devices');

        $response->assertOk()
            ->assertJsonPath('data', []);
    }

    public function test_list_devices_returns_user_devices(): void
    {
        PlayerDevice::factory()->create([
            'id' => 'device_test123',
            'user_id' => $this->user->id,
            'name' => 'Test Device',
            'type' => 'web',
        ]);

        $response = $this->actingAs($this->user)->getJson('/api/v1/player/devices');

        $response->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.device_id', 'device_test123')
            ->assertJsonPath('data.0.name', 'Test Device')
            ->assertJsonPath('data.0.type', 'web');
    }

    public function test_list_devices_does_not_return_other_users_devices(): void
    {
        $otherUser = User::factory()->create();
        PlayerDevice::factory()->create(['user_id' => $this->user->id]);
        PlayerDevice::factory()->create(['user_id' => $otherUser->id]);

        $response = $this->actingAs($this->user)->getJson('/api/v1/player/devices');

        $response->assertOk()
            ->assertJsonCount(1, 'data');
    }

    public function test_list_devices_includes_current_song(): void
    {
        $artist = Artist::factory()->create();
        $album = Album::factory()->create(['artist_id' => $artist->id]);
        $song = Song::factory()->create([
            'artist_id' => $artist->id,
            'album_id' => $album->id,
        ]);

        PlayerDevice::factory()->create([
            'user_id' => $this->user->id,
            'state' => [
                'is_playing' => true,
                'song_id' => $song->id,
                'position' => 45.5,
                'volume' => 0.8,
            ],
        ]);

        $response = $this->actingAs($this->user)->getJson('/api/v1/player/devices');

        $response->assertOk()
            ->assertJsonPath('data.0.is_playing', true)
            ->assertJsonPath('data.0.current_song.id', $song->id)
            ->assertJsonPath('data.0.position', 45.5)
            ->assertJsonPath('data.0.volume', 0.8);
    }

    public function test_register_device_requires_authentication(): void
    {
        $response = $this->postJson('/api/v1/player/devices', [
            'device_id' => 'new_device',
            'name' => 'New Device',
            'type' => 'web',
        ]);

        $response->assertUnauthorized();
    }

    public function test_register_device_creates_new_device(): void
    {
        $response = $this->actingAs($this->user)->postJson('/api/v1/player/devices', [
            'device_id' => 'new_device_123',
            'name' => 'My New Device',
            'type' => 'mobile',
        ]);

        $response->assertCreated()
            ->assertJsonPath('data.device_id', 'new_device_123')
            ->assertJsonPath('data.name', 'My New Device')
            ->assertJsonPath('data.type', 'mobile')
            ->assertJsonPath('data.is_playing', false);

        $this->assertDatabaseHas('player_devices', [
            'id' => 'new_device_123',
            'user_id' => $this->user->id,
            'name' => 'My New Device',
        ]);
    }

    public function test_register_device_updates_existing_device(): void
    {
        PlayerDevice::factory()->create([
            'id' => 'existing_device',
            'user_id' => $this->user->id,
            'name' => 'Old Name',
            'type' => 'web',
        ]);

        $response = $this->actingAs($this->user)->postJson('/api/v1/player/devices', [
            'device_id' => 'existing_device',
            'name' => 'New Name',
            'type' => 'desktop',
        ]);

        $response->assertCreated()
            ->assertJsonPath('data.name', 'New Name')
            ->assertJsonPath('data.type', 'desktop');

        $this->assertDatabaseCount('player_devices', 1);
    }

    public function test_register_device_validates_required_fields(): void
    {
        $response = $this->actingAs($this->user)->postJson('/api/v1/player/devices', []);

        $response->assertUnprocessable()
            ->assertJsonValidationErrors(['device_id', 'name', 'type']);
    }

    public function test_register_device_validates_type_enum(): void
    {
        $response = $this->actingAs($this->user)->postJson('/api/v1/player/devices', [
            'device_id' => 'test',
            'name' => 'Test',
            'type' => 'invalid',
        ]);

        $response->assertUnprocessable()
            ->assertJsonValidationErrors(['type']);
    }

    public function test_unregister_device_requires_authentication(): void
    {
        $response = $this->deleteJson('/api/v1/player/devices/device_123');

        $response->assertUnauthorized();
    }

    public function test_unregister_device_deletes_device(): void
    {
        PlayerDevice::factory()->create([
            'id' => 'device_to_delete',
            'user_id' => $this->user->id,
        ]);

        $response = $this->actingAs($this->user)
            ->deleteJson('/api/v1/player/devices/device_to_delete');

        $response->assertNoContent();

        $this->assertDatabaseMissing('player_devices', ['id' => 'device_to_delete']);
    }

    public function test_unregister_device_returns_404_for_other_users_device(): void
    {
        $otherUser = User::factory()->create();
        PlayerDevice::factory()->create([
            'id' => 'other_device',
            'user_id' => $otherUser->id,
        ]);

        $response = $this->actingAs($this->user)
            ->deleteJson('/api/v1/player/devices/other_device');

        $response->assertNotFound();
    }

    public function test_unregister_device_returns_404_for_nonexistent_device(): void
    {
        $response = $this->actingAs($this->user)
            ->deleteJson('/api/v1/player/devices/nonexistent');

        $response->assertNotFound();
    }
}

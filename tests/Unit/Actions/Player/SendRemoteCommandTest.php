<?php

declare(strict_types=1);

namespace Tests\Unit\Actions\Player;

use App\Actions\Player\SendRemoteCommand;
use App\Events\Player\RemoteCommand;
use App\Exceptions\DeviceNotFoundException;
use App\Models\PlayerDevice;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Event;
use Tests\TestCase;

class SendRemoteCommandTest extends TestCase
{
    use RefreshDatabase;

    private SendRemoteCommand $action;
    private User $user;

    protected function setUp(): void
    {
        parent::setUp();
        $this->action = new SendRemoteCommand();
        $this->user = User::factory()->create();
        Event::fake();
    }

    public function test_sends_command_to_device(): void
    {
        $device = PlayerDevice::factory()->for($this->user)->create();

        $result = $this->action->execute($this->user, $device->id, 'play', []);

        $this->assertEquals($device->id, $result->id);
        Event::assertDispatched(RemoteCommand::class, function ($event) use ($device) {
            return $event->targetDeviceId === $device->id
                && $event->command === 'play'
                && $event->user->id === $this->user->id;
        });
    }

    public function test_sends_command_with_payload(): void
    {
        $device = PlayerDevice::factory()->for($this->user)->create();
        $payload = ['position' => 30.5];

        $this->action->execute($this->user, $device->id, 'seek', $payload);

        Event::assertDispatched(RemoteCommand::class, function ($event) use ($payload) {
            return $event->command === 'seek'
                && $event->payload === $payload;
        });
    }

    public function test_throws_exception_for_nonexistent_device(): void
    {
        $this->expectException(DeviceNotFoundException::class);
        $this->expectExceptionMessage('Target device not found');

        $this->action->execute($this->user, 'nonexistent-device-id', 'play', []);
    }

    public function test_throws_exception_for_device_owned_by_another_user(): void
    {
        $otherUser = User::factory()->create();
        $device = PlayerDevice::factory()->for($otherUser)->create();

        $this->expectException(DeviceNotFoundException::class);

        $this->action->execute($this->user, $device->id, 'play', []);
    }

    public function test_returns_the_device(): void
    {
        $device = PlayerDevice::factory()->for($this->user)->create(['name' => 'iPhone']);

        $result = $this->action->execute($this->user, $device->id, 'pause', []);

        $this->assertInstanceOf(PlayerDevice::class, $result);
        $this->assertEquals('iPhone', $result->name);
    }
}

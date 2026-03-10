<?php

declare(strict_types=1);

namespace Tests\Unit\Actions\Player;

use App\Actions\Player\SyncDeviceQueue;
use App\Events\Player\QueueUpdated;
use App\Models\PlayerDevice;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Event;
use Tests\TestCase;

class SyncDeviceQueueTest extends TestCase
{
    use RefreshDatabase;

    private SyncDeviceQueue $action;
    private User $user;

    protected function setUp(): void
    {
        parent::setUp();
        $this->action = new SyncDeviceQueue();
        $this->user = User::factory()->create();
        Event::fake();
    }

    public function test_broadcasts_queue_update(): void
    {
        $queue = ['song-1', 'song-2', 'song-3'];

        $this->action->execute($this->user, $queue, 0, 0.0);

        Event::assertDispatched(QueueUpdated::class, function ($event) use ($queue) {
            return $event->queue === $queue
                && $event->currentIndex === 0
                && $event->position === 0.0
                && $event->user->id === $this->user->id;
        });
    }

    public function test_broadcasts_with_current_index_and_position(): void
    {
        $queue = ['song-1', 'song-2'];

        $this->action->execute($this->user, $queue, 1, 42.5);

        Event::assertDispatched(QueueUpdated::class, function ($event) {
            return $event->currentIndex === 1
                && $event->position === 42.5;
        });
    }

    public function test_returns_count_of_online_devices(): void
    {
        // Create 2 online devices
        PlayerDevice::factory()->for($this->user)->create([
            'last_seen' => now(),
        ]);
        PlayerDevice::factory()->for($this->user)->create([
            'last_seen' => now()->subSeconds(30),
        ]);
        // Create 1 offline device
        PlayerDevice::factory()->for($this->user)->create([
            'last_seen' => now()->subMinutes(5),
        ]);

        $count = $this->action->execute($this->user, [], 0, 0.0);

        $this->assertEquals(2, $count);
    }

    public function test_returns_zero_when_no_online_devices(): void
    {
        // Create only offline devices
        PlayerDevice::factory()->for($this->user)->create([
            'last_seen' => now()->subMinutes(5),
        ]);

        $count = $this->action->execute($this->user, [], 0, 0.0);

        $this->assertEquals(0, $count);
    }

    public function test_does_not_count_other_users_devices(): void
    {
        $otherUser = User::factory()->create();

        PlayerDevice::factory()->for($this->user)->create([
            'last_seen' => now(),
        ]);
        PlayerDevice::factory()->for($otherUser)->create([
            'last_seen' => now(),
        ]);

        $count = $this->action->execute($this->user, [], 0, 0.0);

        $this->assertEquals(1, $count);
    }

    public function test_handles_empty_queue(): void
    {
        $count = $this->action->execute($this->user, [], 0, 0.0);

        $this->assertSame(0, $count);
        Event::assertDispatched(QueueUpdated::class, function ($event) {
            return $event->queue === [];
        });
    }

    public function test_includes_device_at_exact_boundary(): void
    {
        PlayerDevice::factory()->for($this->user)->create([
            'last_seen' => now()->subMinutes(1),
        ]);

        $count = $this->action->execute($this->user, [], 0, 0.0);

        $this->assertSame(1, $count);
    }
}

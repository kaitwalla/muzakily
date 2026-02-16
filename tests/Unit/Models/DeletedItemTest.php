<?php

declare(strict_types=1);

namespace Tests\Unit\Models;

use App\Models\DeletedItem;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Tests\TestCase;

class DeletedItemTest extends TestCase
{
    use RefreshDatabase;

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        parent::tearDown();
    }

    public function test_can_record_deletion(): void
    {
        $deletedItem = DeletedItem::recordDeletion('song', 'abc-123-def');

        $this->assertDatabaseHas('deleted_items', [
            'deletable_type' => 'song',
            'deletable_id' => 'abc-123-def',
            'user_id' => null,
        ]);
        $this->assertNotNull($deletedItem->deleted_at);
    }

    public function test_can_record_deletion_with_user_id(): void
    {
        $user = User::factory()->create();

        $deletedItem = DeletedItem::recordDeletion('playlist', 'playlist-uuid', $user->id);

        $this->assertDatabaseHas('deleted_items', [
            'deletable_type' => 'playlist',
            'deletable_id' => 'playlist-uuid',
            'user_id' => $user->id,
        ]);
    }

    public function test_scope_of_type_filters_by_type(): void
    {
        DeletedItem::recordDeletion('song', 'song-1');
        DeletedItem::recordDeletion('song', 'song-2');
        DeletedItem::recordDeletion('album', 'album-1');

        $songs = DeletedItem::ofType('song')->get();

        $this->assertCount(2, $songs);
        $this->assertTrue($songs->every(fn ($item) => $item->deletable_type === 'song'));
    }

    public function test_scope_since_filters_by_deletion_time(): void
    {
        Carbon::setTestNow('2024-01-15 12:00:00');
        DeletedItem::recordDeletion('song', 'old-song');

        Carbon::setTestNow('2024-01-16 12:00:00');
        DeletedItem::recordDeletion('song', 'new-song');

        $since = Carbon::parse('2024-01-16 00:00:00');
        $items = DeletedItem::since($since)->get();

        $this->assertCount(1, $items);
        $this->assertEquals('new-song', $items->first()->deletable_id);
    }

    public function test_scope_for_user_filters_by_user(): void
    {
        $user1 = User::factory()->create();
        $user2 = User::factory()->create();

        DeletedItem::recordDeletion('playlist', 'playlist-1', $user1->id);
        DeletedItem::recordDeletion('playlist', 'playlist-2', $user2->id);
        DeletedItem::recordDeletion('song', 'song-1'); // No user

        $user1Items = DeletedItem::forUser($user1)->get();

        $this->assertCount(1, $user1Items);
        $this->assertEquals('playlist-1', $user1Items->first()->deletable_id);
    }

    public function test_scope_for_user_with_null_returns_items_without_user(): void
    {
        $user = User::factory()->create();

        DeletedItem::recordDeletion('playlist', 'playlist-1', $user->id);
        DeletedItem::recordDeletion('song', 'song-1'); // No user

        $nullUserItems = DeletedItem::forUser(null)->get();

        $this->assertCount(1, $nullUserItems);
        $this->assertEquals('song-1', $nullUserItems->first()->deletable_id);
    }

    public function test_can_chain_scopes(): void
    {
        $user = User::factory()->create();

        // Old item - created yesterday
        Carbon::setTestNow('2024-01-14 12:00:00');
        DeletedItem::recordDeletion('playlist', 'old-playlist', $user->id);

        // Recent items - created today
        Carbon::setTestNow('2024-01-15 12:00:00');
        DeletedItem::recordDeletion('playlist', 'new-playlist', $user->id);
        DeletedItem::recordDeletion('song', 'new-song');

        // Query for items since this morning
        $since = Carbon::parse('2024-01-15 00:00:00');

        $items = DeletedItem::ofType('playlist')
            ->forUser($user)
            ->since($since)
            ->get();

        $this->assertCount(1, $items);
        $this->assertEquals('new-playlist', $items->first()->deletable_id);
    }

    public function test_user_relationship(): void
    {
        $user = User::factory()->create();
        $deletedItem = DeletedItem::recordDeletion('playlist', 'playlist-1', $user->id);

        $this->assertEquals($user->id, $deletedItem->user->id);
    }

    public function test_deleted_at_is_cast_to_datetime(): void
    {
        $deletedItem = DeletedItem::recordDeletion('song', 'song-1');

        $this->assertInstanceOf(Carbon::class, $deletedItem->deleted_at);
    }
}

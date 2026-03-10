<?php

declare(strict_types=1);

namespace Tests\Contracts\Api\V1;

use App\Enums\DownloadRequestStatus;
use App\Models\DownloadRequest;
use App\Models\Tag;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Event;
use Tests\TestCase;

class DownloadRequestContractTest extends TestCase
{
    use RefreshDatabase;

    private User $user;

    protected function setUp(): void
    {
        parent::setUp();
        $this->user = User::factory()->create();
        Event::fake();
    }

    public function test_store_returns_correct_structure(): void
    {
        $tag = Tag::factory()->create();

        $response = $this->actingAs($this->user)->postJson('/api/v1/downloads', [
            'url' => 'https://tidal.com/browse/track/123456',
            'tag_ids' => [$tag->id],
        ]);

        $response->assertCreated()
            ->assertJsonStructure([
                'data' => [
                    'id',
                    'user_id',
                    'url',
                    'tag_ids',
                    'status',
                    'song_id',
                    'error',
                    'created_at',
                    'updated_at',
                ],
            ]);
    }

    public function test_store_returns_pending_status(): void
    {
        $response = $this->actingAs($this->user)->postJson('/api/v1/downloads', [
            'url' => 'https://tidal.com/browse/track/123456',
        ]);

        $response->assertCreated()
            ->assertJsonPath('data.status', DownloadRequestStatus::PENDING->value)
            ->assertJsonPath('data.song_id', null)
            ->assertJsonPath('data.error', null);
    }

    public function test_show_returns_correct_structure(): void
    {
        $downloadRequest = DownloadRequest::create([
            'user_id' => $this->user->id,
            'url' => 'https://tidal.com/browse/track/123456',
            'tag_ids' => [],
            'status' => DownloadRequestStatus::PENDING,
        ]);

        $response = $this->actingAs($this->user)->getJson("/api/v1/downloads/{$downloadRequest->id}");

        $response->assertOk()
            ->assertJsonStructure([
                'data' => [
                    'id',
                    'user_id',
                    'url',
                    'tag_ids',
                    'status',
                    'song_id',
                    'error',
                    'created_at',
                    'updated_at',
                ],
            ]);
    }

    public function test_index_returns_correct_structure(): void
    {
        DownloadRequest::create([
            'user_id' => $this->user->id,
            'url' => 'https://tidal.com/browse/track/123456',
            'tag_ids' => [],
            'status' => DownloadRequestStatus::PENDING,
        ]);

        $response = $this->actingAs($this->user)->getJson('/api/v1/downloads');

        $response->assertOk()
            ->assertJsonStructure([
                'data' => [
                    '*' => [
                        'id',
                        'user_id',
                        'url',
                        'tag_ids',
                        'status',
                        'song_id',
                        'error',
                        'created_at',
                        'updated_at',
                    ],
                ],
            ]);
    }
}

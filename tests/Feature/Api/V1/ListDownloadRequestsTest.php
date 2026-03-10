<?php

declare(strict_types=1);

namespace Tests\Feature\Api\V1;

use App\Enums\DownloadRequestStatus;
use App\Models\DownloadRequest;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ListDownloadRequestsTest extends TestCase
{
    use RefreshDatabase;

    private User $user;
    private User $otherUser;

    protected function setUp(): void
    {
        parent::setUp();
        $this->user = User::factory()->create();
        $this->otherUser = User::factory()->create();
    }

    public function test_index_requires_authentication(): void
    {
        $response = $this->getJson('/api/v1/downloads');

        $response->assertUnauthorized();
    }

    public function test_index_returns_only_authenticated_users_requests(): void
    {
        DownloadRequest::create([
            'user_id' => $this->user->id,
            'url' => 'https://tidal.com/browse/track/111',
            'tag_ids' => [],
            'status' => DownloadRequestStatus::PENDING,
        ]);

        DownloadRequest::create([
            'user_id' => $this->otherUser->id,
            'url' => 'https://tidal.com/browse/track/222',
            'tag_ids' => [],
            'status' => DownloadRequestStatus::PENDING,
        ]);

        $response = $this->actingAs($this->user)->getJson('/api/v1/downloads');

        $response->assertOk();
        $data = $response->json('data');
        $this->assertCount(1, $data);
        $this->assertSame('https://tidal.com/browse/track/111', $data[0]['url']);
    }

    public function test_index_returns_latest_20_requests(): void
    {
        for ($i = 1; $i <= 25; $i++) {
            DownloadRequest::create([
                'user_id' => $this->user->id,
                'url' => "https://tidal.com/browse/track/{$i}",
                'tag_ids' => [],
                'status' => DownloadRequestStatus::PENDING,
            ]);
        }

        $response = $this->actingAs($this->user)->getJson('/api/v1/downloads');

        $response->assertOk();
        $this->assertCount(20, $response->json('data'));
    }

    public function test_show_requires_authentication(): void
    {
        $request = DownloadRequest::create([
            'user_id' => $this->user->id,
            'url' => 'https://tidal.com/browse/track/111',
            'tag_ids' => [],
            'status' => DownloadRequestStatus::PENDING,
        ]);

        $response = $this->getJson("/api/v1/downloads/{$request->id}");

        $response->assertUnauthorized();
    }

    public function test_show_returns_download_request_for_owner(): void
    {
        $request = DownloadRequest::create([
            'user_id' => $this->user->id,
            'url' => 'https://tidal.com/browse/track/111',
            'tag_ids' => [],
            'status' => DownloadRequestStatus::PENDING,
        ]);

        $response = $this->actingAs($this->user)->getJson("/api/v1/downloads/{$request->id}");

        $response->assertOk()
            ->assertJsonPath('data.id', $request->id);
    }

    public function test_show_returns_403_for_other_users_request(): void
    {
        $request = DownloadRequest::create([
            'user_id' => $this->otherUser->id,
            'url' => 'https://tidal.com/browse/track/111',
            'tag_ids' => [],
            'status' => DownloadRequestStatus::PENDING,
        ]);

        $response = $this->actingAs($this->user)->getJson("/api/v1/downloads/{$request->id}");

        $response->assertForbidden();
    }
}

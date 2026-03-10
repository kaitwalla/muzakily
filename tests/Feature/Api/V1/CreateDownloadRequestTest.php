<?php

declare(strict_types=1);

namespace Tests\Feature\Api\V1;

use App\Enums\DownloadRequestStatus;
use App\Events\DownloadRequested;
use App\Models\DownloadRequest;
use App\Models\Tag;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Event;
use Tests\TestCase;

class CreateDownloadRequestTest extends TestCase
{
    use RefreshDatabase;

    private User $user;

    protected function setUp(): void
    {
        parent::setUp();
        $this->user = User::factory()->create();
        Event::fake();
    }

    public function test_create_requires_authentication(): void
    {
        $response = $this->postJson('/api/v1/downloads', [
            'url' => 'https://tidal.com/browse/track/123456',
        ]);

        $response->assertUnauthorized();
    }

    public function test_create_validates_url_required(): void
    {
        $response = $this->actingAs($this->user)->postJson('/api/v1/downloads', []);

        $response->assertUnprocessable()
            ->assertJsonValidationErrors(['url']);
    }

    public function test_create_validates_url_format(): void
    {
        $response = $this->actingAs($this->user)->postJson('/api/v1/downloads', [
            'url' => 'not-a-url',
        ]);

        $response->assertUnprocessable()
            ->assertJsonValidationErrors(['url']);
    }

    public function test_create_validates_tag_ids_must_exist(): void
    {
        $response = $this->actingAs($this->user)->postJson('/api/v1/downloads', [
            'url' => 'https://tidal.com/browse/track/123456',
            'tag_ids' => [99999],
        ]);

        $response->assertUnprocessable()
            ->assertJsonValidationErrors(['tag_ids.0']);
    }

    public function test_create_returns_201_with_download_request(): void
    {
        $tag = Tag::factory()->create();

        $response = $this->actingAs($this->user)->postJson('/api/v1/downloads', [
            'url' => 'https://tidal.com/browse/track/123456',
            'tag_ids' => [$tag->id],
        ]);

        $response->assertCreated()
            ->assertJsonStructure([
                'data' => ['id', 'url', 'tag_ids', 'status', 'created_at'],
            ])
            ->assertJsonPath('data.status', DownloadRequestStatus::PENDING->value)
            ->assertJsonPath('data.url', 'https://tidal.com/browse/track/123456');

        $this->assertDatabaseHas('download_requests', [
            'user_id' => $this->user->id,
            'url' => 'https://tidal.com/browse/track/123456',
            'status' => DownloadRequestStatus::PENDING->value,
        ]);
    }

    public function test_create_fires_download_requested_event(): void
    {
        $response = $this->actingAs($this->user)->postJson('/api/v1/downloads', [
            'url' => 'https://tidal.com/browse/track/123456',
        ]);

        $response->assertCreated();

        Event::assertDispatched(DownloadRequested::class, function (DownloadRequested $event): bool {
            return $event->user->id === $this->user->id
                && $event->downloadRequest->url === 'https://tidal.com/browse/track/123456';
        });
    }

    public function test_create_works_without_tag_ids(): void
    {
        $response = $this->actingAs($this->user)->postJson('/api/v1/downloads', [
            'url' => 'https://tidal.com/browse/track/123456',
        ]);

        $response->assertCreated();

        $request = DownloadRequest::where('user_id', $this->user->id)->first();
        $this->assertNotNull($request);
        $this->assertSame([], $request->tag_ids);
    }
}

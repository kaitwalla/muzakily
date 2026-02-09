<?php

declare(strict_types=1);

namespace Tests\Feature\Api\V1\Admin;

use App\Jobs\ScanR2BucketJob;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Queue;
use Tests\TestCase;

class LibraryScanTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;
    private User $user;

    protected function setUp(): void
    {
        parent::setUp();
        $this->admin = User::factory()->admin()->create();
        $this->user = User::factory()->create();
        Queue::fake();
    }

    public function test_trigger_scan_requires_authentication(): void
    {
        $response = $this->postJson('/api/v1/admin/library/scan');

        $response->assertUnauthorized();
    }

    public function test_trigger_scan_requires_admin_role(): void
    {
        $response = $this->actingAs($this->user)->postJson('/api/v1/admin/library/scan');

        $response->assertForbidden();
    }

    public function test_admin_can_trigger_scan(): void
    {
        $response = $this->actingAs($this->admin)->postJson('/api/v1/admin/library/scan');

        $response->assertAccepted()
            ->assertJsonStructure([
                'data' => ['job_id', 'status'],
            ])
            ->assertJsonPath('data.status', 'started');

        Queue::assertPushed(ScanR2BucketJob::class);
    }

    public function test_scan_with_force_flag(): void
    {
        $response = $this->actingAs($this->admin)->postJson('/api/v1/admin/library/scan', [
            'force' => true,
        ]);

        $response->assertAccepted();

        Queue::assertPushed(ScanR2BucketJob::class, function ($job) {
            return $job->force === true;
        });
    }

    public function test_scan_stores_status_in_cache(): void
    {
        Cache::flush();

        $response = $this->actingAs($this->admin)->postJson('/api/v1/admin/library/scan');

        $response->assertAccepted();

        $jobId = $response->json('data.job_id');
        $status = Cache::get("scan_status:{$jobId}");

        $this->assertNotNull($status);
        $this->assertEquals('started', $status['status']);
        $this->assertArrayHasKey('progress', $status);
        $this->assertArrayHasKey('started_at', $status);
    }

    public function test_get_scan_status_requires_authentication(): void
    {
        $response = $this->getJson('/api/v1/admin/library/scan/status');

        $response->assertUnauthorized();
    }

    public function test_get_scan_status_requires_admin_role(): void
    {
        $response = $this->actingAs($this->user)->getJson('/api/v1/admin/library/scan/status');

        $response->assertForbidden();
    }

    public function test_get_scan_status_returns_idle_when_no_scan(): void
    {
        Cache::flush();

        $response = $this->actingAs($this->admin)->getJson('/api/v1/admin/library/scan/status');

        $response->assertOk()
            ->assertJsonPath('data.status', 'idle');
    }

    public function test_get_scan_status_returns_current_status(): void
    {
        $jobId = 'test_scan_123';
        Cache::put('scan_current_job', $jobId, 3600);
        Cache::put("scan_status:{$jobId}", [
            'status' => 'in_progress',
            'progress' => [
                'total_files' => 100,
                'scanned_files' => 50,
                'new_songs' => 20,
                'updated_songs' => 5,
                'errors' => 1,
            ],
            'started_at' => now()->toIso8601String(),
        ], 3600);

        $response = $this->actingAs($this->admin)->getJson('/api/v1/admin/library/scan/status');

        $response->assertOk()
            ->assertJsonPath('data.status', 'in_progress')
            ->assertJsonPath('data.progress.total_files', 100)
            ->assertJsonPath('data.progress.scanned_files', 50);
    }

    public function test_scan_validates_force_is_boolean(): void
    {
        $response = $this->actingAs($this->admin)->postJson('/api/v1/admin/library/scan', [
            'force' => 'not-a-boolean',
        ]);

        $response->assertUnprocessable()
            ->assertJsonValidationErrors(['force']);
    }
}

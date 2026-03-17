<?php

declare(strict_types=1);

namespace Tests\Feature\Api\V1;

use App\Models\User;
use Tests\TestCase;

class CompanionChannelTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        config([
            'broadcasting.default' => 'pusher',
            'broadcasting.connections.pusher.key' => 'test-key',
            'broadcasting.connections.pusher.secret' => 'test-secret',
            'broadcasting.connections.pusher.app_id' => 'test-app-id',
        ]);

        // Channels are registered on the null broadcaster during app boot (phpunit.xml default).
        // Re-requiring the channels file registers them on the now-active pusher broadcaster.
        require base_path('routes/channels.php');
    }

    public function test_owner_can_join_presence_companion_channel(): void
    {
        $user = User::factory()->create();

        $response = $this->actingAs($user)->postJson('/broadcasting/auth', [
            'socket_id' => '123456.7890',
            'channel_name' => "presence-companion.{$user->uuid}",
        ]);

        $response->assertOk()
            ->assertJsonStructure(['auth', 'channel_data']);
    }

    public function test_web_member_type_returned_without_companion_header(): void
    {
        $user = User::factory()->create();

        $response = $this->actingAs($user)->postJson('/broadcasting/auth', [
            'socket_id' => '123456.7890',
            'channel_name' => "presence-companion.{$user->uuid}",
        ]);

        $response->assertOk();

        /** @var array<string, mixed> $channelData */
        $channelData = json_decode((string) $response->json('channel_data'), true);
        $this->assertSame('web', $channelData['user_info']['type']);
    }

    public function test_companion_member_type_returned_with_companion_header(): void
    {
        $user = User::factory()->create();

        $response = $this->actingAs($user)
            ->withHeaders(['X-Companion' => '1'])
            ->postJson('/broadcasting/auth', [
                'socket_id' => '123456.7890',
                'channel_name' => "presence-companion.{$user->uuid}",
            ]);

        $response->assertOk();

        /** @var array<string, mixed> $channelData */
        $channelData = json_decode((string) $response->json('channel_data'), true);
        $this->assertSame('companion', $channelData['user_info']['type']);
    }

    public function test_companion_reports_gamdl_available_when_header_set(): void
    {
        $user = User::factory()->create();

        $response = $this->actingAs($user)
            ->withHeaders(['X-Companion' => '1', 'X-Companion-Gamdl' => '1'])
            ->postJson('/broadcasting/auth', [
                'socket_id' => '123456.7890',
                'channel_name' => "presence-companion.{$user->uuid}",
            ]);

        $response->assertOk();

        /** @var array<string, mixed> $channelData */
        $channelData = json_decode((string) $response->json('channel_data'), true);
        $this->assertTrue($channelData['user_info']['gamdl_available']);
    }

    public function test_companion_reports_gamdl_unavailable_when_header_not_set(): void
    {
        $user = User::factory()->create();

        $response = $this->actingAs($user)
            ->withHeaders(['X-Companion' => '1'])
            ->postJson('/broadcasting/auth', [
                'socket_id' => '123456.7890',
                'channel_name' => "presence-companion.{$user->uuid}",
            ]);

        $response->assertOk();

        /** @var array<string, mixed> $channelData */
        $channelData = json_decode((string) $response->json('channel_data'), true);
        $this->assertFalse($channelData['user_info']['gamdl_available']);
    }

    public function test_owner_can_auth_private_user_channel(): void
    {
        $user = User::factory()->create();

        $response = $this->actingAs($user)->postJson('/broadcasting/auth', [
            'socket_id' => '123456.7890',
            'channel_name' => "private-user.{$user->uuid}",
        ]);

        $response->assertOk()->assertJsonStructure(['auth']);
    }

    public function test_other_user_cannot_auth_private_user_channel(): void
    {
        $owner = User::factory()->create();
        $other = User::factory()->create();

        $response = $this->actingAs($other)->postJson('/broadcasting/auth', [
            'socket_id' => '123456.7890',
            'channel_name' => "private-user.{$owner->uuid}",
        ]);

        $response->assertForbidden();
    }

    public function test_other_user_cannot_join_channel(): void
    {
        $owner = User::factory()->create();
        $other = User::factory()->create();

        $response = $this->actingAs($other)->postJson('/broadcasting/auth', [
            'socket_id' => '123456.7890',
            'channel_name' => "presence-companion.{$owner->uuid}",
        ]);

        $response->assertForbidden();
    }

    public function test_unauthenticated_user_cannot_join_channel(): void
    {
        $user = User::factory()->create();

        $response = $this->postJson('/broadcasting/auth', [
            'socket_id' => '123456.7890',
            'channel_name' => "presence-companion.{$user->uuid}",
        ]);

        $response->assertForbidden();
    }
}

<?php

declare(strict_types=1);

namespace Tests\Feature\Api\V1;

use Tests\TestCase;

class ConfigEndpointTest extends TestCase
{
    public function test_config_returns_pusher_configuration(): void
    {
        config([
            'broadcasting.connections.pusher.key' => 'test-app-key',
            'broadcasting.connections.pusher.options.cluster' => 'us2',
        ]);

        $response = $this->getJson('/api/v1/config');

        $response->assertOk()
            ->assertExactJson([
                'pusher_key' => 'test-app-key',
                'pusher_cluster' => 'us2',
            ]);
    }

    public function test_config_returns_null_when_pusher_not_configured(): void
    {
        config([
            'broadcasting.connections.pusher.key' => null,
            'broadcasting.connections.pusher.options.cluster' => null,
        ]);

        $response = $this->getJson('/api/v1/config');

        $response->assertOk()
            ->assertExactJson([
                'pusher_key' => null,
                'pusher_cluster' => null,
            ]);
    }

    public function test_config_endpoint_is_public(): void
    {
        // Ensure the endpoint does not require authentication
        $response = $this->getJson('/api/v1/config');

        $response->assertOk();
    }
}

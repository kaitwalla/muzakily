<?php

declare(strict_types=1);

namespace Tests\Feature\Api\V1;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AuthEndpointTest extends TestCase
{
    use RefreshDatabase;

    private User $user;

    protected function setUp(): void
    {
        parent::setUp();
        $this->user = User::factory()->create();
    }

    public function test_me_requires_authentication(): void
    {
        $response = $this->getJson('/api/v1/auth/me');

        $response->assertUnauthorized();
    }

    public function test_me_returns_user_data(): void
    {
        $response = $this->actingAs($this->user)->getJson('/api/v1/auth/me');

        $response->assertOk()
            ->assertJsonPath('data.id', $this->user->id)
            ->assertJsonPath('data.name', $this->user->name)
            ->assertJsonPath('data.email', $this->user->email)
            ->assertJsonPath('data.preferences', []);
    }

    public function test_update_profile_requires_authentication(): void
    {
        $response = $this->patchJson('/api/v1/auth/me', [
            'name' => 'New Name',
        ]);

        $response->assertUnauthorized();
    }

    public function test_update_profile_updates_name(): void
    {
        $response = $this->actingAs($this->user)->patchJson('/api/v1/auth/me', [
            'name' => 'Updated Name',
        ]);

        $response->assertOk()
            ->assertJsonPath('data.name', 'Updated Name');

        $this->assertDatabaseHas('users', [
            'id' => $this->user->id,
            'name' => 'Updated Name',
        ]);
    }

    public function test_update_profile_validates_name_max_length(): void
    {
        $response = $this->actingAs($this->user)->patchJson('/api/v1/auth/me', [
            'name' => str_repeat('a', 256),
        ]);

        $response->assertUnprocessable()
            ->assertJsonValidationErrors(['name']);
    }

    public function test_update_profile_updates_preferences(): void
    {
        $response = $this->actingAs($this->user)->patchJson('/api/v1/auth/me', [
            'preferences' => [
                'audio_quality' => 'high',
                'crossfade' => 5,
            ],
        ]);

        $response->assertOk()
            ->assertJsonPath('data.preferences.audio_quality', 'high')
            ->assertJsonPath('data.preferences.crossfade', 5);

        $this->user->refresh();
        $this->assertEquals('high', $this->user->getPreference('audio_quality'));
        $this->assertEquals(5, $this->user->getPreference('crossfade'));
    }

    public function test_update_profile_validates_audio_quality(): void
    {
        $response = $this->actingAs($this->user)->patchJson('/api/v1/auth/me', [
            'preferences' => [
                'audio_quality' => 'invalid',
            ],
        ]);

        $response->assertUnprocessable()
            ->assertJsonValidationErrors(['preferences.audio_quality']);
    }

    public function test_update_profile_validates_crossfade(): void
    {
        $response = $this->actingAs($this->user)->patchJson('/api/v1/auth/me', [
            'preferences' => [
                'crossfade' => 7, // Not in allowed values
            ],
        ]);

        $response->assertUnprocessable()
            ->assertJsonValidationErrors(['preferences.crossfade']);
    }

    public function test_update_profile_merges_preferences(): void
    {
        // Set initial preferences
        $this->user->preferences = ['audio_quality' => 'low'];
        $this->user->save();

        $response = $this->actingAs($this->user)->patchJson('/api/v1/auth/me', [
            'preferences' => [
                'crossfade' => 3,
            ],
        ]);

        $response->assertOk()
            ->assertJsonPath('data.preferences.audio_quality', 'low')
            ->assertJsonPath('data.preferences.crossfade', 3);

        $this->user->refresh();
        $this->assertEquals('low', $this->user->getPreference('audio_quality'));
        $this->assertEquals(3, $this->user->getPreference('crossfade'));
    }

    public function test_update_profile_updates_name_and_preferences(): void
    {
        $response = $this->actingAs($this->user)->patchJson('/api/v1/auth/me', [
            'name' => 'New Name',
            'preferences' => [
                'audio_quality' => 'auto',
            ],
        ]);

        $response->assertOk()
            ->assertJsonPath('data.name', 'New Name')
            ->assertJsonPath('data.preferences.audio_quality', 'auto');
    }

    public function test_me_returns_preferences(): void
    {
        $this->user->preferences = [
            'audio_quality' => 'high',
            'crossfade' => 5,
        ];
        $this->user->save();

        $response = $this->actingAs($this->user)->getJson('/api/v1/auth/me');

        $response->assertOk()
            ->assertJsonPath('data.preferences.audio_quality', 'high')
            ->assertJsonPath('data.preferences.crossfade', 5);
    }
}

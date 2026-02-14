<?php

declare(strict_types=1);

namespace Tests\Unit\Actions\Auth;

use App\Actions\Auth\UpdateUserProfile;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class UpdateUserProfileTest extends TestCase
{
    use RefreshDatabase;

    private UpdateUserProfile $action;

    protected function setUp(): void
    {
        parent::setUp();
        $this->action = new UpdateUserProfile();
        Storage::fake('public');
    }

    public function test_updates_user_name(): void
    {
        $user = User::factory()->create(['name' => 'Old Name']);

        $result = $this->action->execute($user, ['name' => 'New Name']);

        $this->assertEquals('New Name', $result->name);
        $this->assertDatabaseHas('users', ['id' => $user->id, 'name' => 'New Name']);
    }

    public function test_updates_user_preferences(): void
    {
        $user = User::factory()->create(['preferences' => ['audio_quality' => 'auto']]);

        $result = $this->action->execute($user, [
            'preferences' => ['crossfade' => 5],
        ]);

        $this->assertEquals('auto', $result->preferences['audio_quality']);
        $this->assertEquals(5, $result->preferences['crossfade']);
    }

    public function test_merges_preferences_with_existing(): void
    {
        $user = User::factory()->create([
            'preferences' => [
                'audio_quality' => 'high',
                'crossfade' => 3,
            ],
        ]);

        $result = $this->action->execute($user, [
            'preferences' => ['audio_quality' => 'low'],
        ]);

        $this->assertEquals('low', $result->preferences['audio_quality']);
        $this->assertEquals(3, $result->preferences['crossfade']);
    }

    public function test_updates_password(): void
    {
        $user = User::factory()->create();
        $oldPassword = $user->password;

        $result = $this->action->execute($user, ['password' => 'newpassword123']);

        $this->assertNotEquals($oldPassword, $result->password);
    }

    public function test_uploads_avatar(): void
    {
        $user = User::factory()->create();
        $avatar = UploadedFile::fake()->create('avatar.jpg', 100, 'image/jpeg');

        $result = $this->action->execute($user, [], $avatar);

        $this->assertNotNull($result->avatar_path);
        Storage::disk('public')->assertExists($result->avatar_path);
    }

    public function test_deletes_old_avatar_when_uploading_new(): void
    {
        $user = User::factory()->create(['avatar_path' => 'avatars/old.jpg']);
        Storage::disk('public')->put('avatars/old.jpg', 'content');

        $avatar = UploadedFile::fake()->create('new.jpg', 100, 'image/jpeg');

        $result = $this->action->execute($user, [], $avatar);

        Storage::disk('public')->assertMissing('avatars/old.jpg');
        $this->assertNotNull($result->avatar_path);
        $this->assertNotEquals('avatars/old.jpg', $result->avatar_path);
    }

    public function test_only_updates_provided_fields(): void
    {
        $user = User::factory()->create([
            'name' => 'Original',
            'preferences' => ['audio_quality' => 'high'],
        ]);

        $result = $this->action->execute($user, ['name' => 'Updated']);

        $this->assertEquals('Updated', $result->name);
        $this->assertEquals('high', $result->preferences['audio_quality']);
    }

    public function test_handles_null_preferences(): void
    {
        $user = User::factory()->create(['preferences' => null]);

        $result = $this->action->execute($user, [
            'preferences' => ['audio_quality' => 'high'],
        ]);

        $this->assertEquals('high', $result->preferences['audio_quality']);
    }
}

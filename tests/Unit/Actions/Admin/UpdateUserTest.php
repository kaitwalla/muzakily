<?php

declare(strict_types=1);

namespace Tests\Unit\Actions\Admin;

use App\Actions\Admin\UpdateUser;
use App\Enums\UserRole;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class UpdateUserTest extends TestCase
{
    use RefreshDatabase;

    private UpdateUser $action;

    protected function setUp(): void
    {
        parent::setUp();
        $this->action = new UpdateUser();
    }

    public function test_updates_user_name(): void
    {
        $user = User::factory()->create(['name' => 'Old Name']);

        $result = $this->action->execute($user, ['name' => 'New Name']);

        $this->assertEquals('New Name', $result->name);
        $this->assertDatabaseHas('users', ['id' => $user->id, 'name' => 'New Name']);
    }

    public function test_updates_user_email(): void
    {
        $user = User::factory()->create(['email' => 'old@example.com']);

        $result = $this->action->execute($user, ['email' => 'new@example.com']);

        $this->assertEquals('new@example.com', $result->email);
    }

    public function test_updates_user_password(): void
    {
        $user = User::factory()->create();

        $result = $this->action->execute($user, ['password' => 'newpassword123']);

        $this->assertTrue(Hash::check('newpassword123', $result->password));
    }

    public function test_updates_user_role(): void
    {
        $user = User::factory()->create(['role' => UserRole::USER]);

        $result = $this->action->execute($user, ['role' => 'admin']);

        $this->assertEquals(UserRole::ADMIN, $result->role);
    }

    public function test_defaults_to_user_role_for_invalid_role(): void
    {
        $user = User::factory()->create(['role' => UserRole::ADMIN]);

        $result = $this->action->execute($user, ['role' => 'invalid']);

        $this->assertEquals(UserRole::USER, $result->role);
    }

    public function test_only_updates_provided_fields(): void
    {
        $user = User::factory()->create([
            'name' => 'Original Name',
            'email' => 'original@example.com',
        ]);

        $result = $this->action->execute($user, ['name' => 'New Name']);

        $this->assertEquals('New Name', $result->name);
        $this->assertEquals('original@example.com', $result->email);
    }

    public function test_returns_refreshed_user_instance(): void
    {
        $user = User::factory()->create(['name' => 'Old']);

        $result = $this->action->execute($user, ['name' => 'New']);

        $this->assertEquals('New', $result->name);
        $this->assertSame($user, $result);
    }

    public function test_updates_multiple_fields(): void
    {
        $user = User::factory()->create([
            'name' => 'Old Name',
            'email' => 'old@example.com',
            'role' => UserRole::USER,
        ]);

        $result = $this->action->execute($user, [
            'name' => 'New Name',
            'email' => 'new@example.com',
            'role' => 'admin',
        ]);

        $this->assertEquals('New Name', $result->name);
        $this->assertEquals('new@example.com', $result->email);
        $this->assertEquals(UserRole::ADMIN, $result->role);
    }
}

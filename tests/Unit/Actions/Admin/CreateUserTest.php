<?php

declare(strict_types=1);

namespace Tests\Unit\Actions\Admin;

use App\Actions\Admin\CreateUser;
use App\Enums\UserRole;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class CreateUserTest extends TestCase
{
    use RefreshDatabase;

    private CreateUser $action;

    protected function setUp(): void
    {
        parent::setUp();
        $this->action = new CreateUser();
    }

    public function test_creates_user_with_required_fields(): void
    {
        $user = $this->action->execute([
            'name' => 'Test User',
            'email' => 'test@example.com',
            'password' => 'password123',
        ]);

        $this->assertInstanceOf(User::class, $user);
        $this->assertEquals('Test User', $user->name);
        $this->assertEquals('test@example.com', $user->email);
        $this->assertDatabaseHas('users', [
            'name' => 'Test User',
            'email' => 'test@example.com',
        ]);
    }

    public function test_hashes_password(): void
    {
        $user = $this->action->execute([
            'name' => 'Test User',
            'email' => 'test@example.com',
            'password' => 'password123',
        ]);

        $this->assertTrue(Hash::check('password123', $user->password));
    }

    public function test_creates_user_with_admin_role(): void
    {
        $user = $this->action->execute([
            'name' => 'Admin User',
            'email' => 'admin@example.com',
            'password' => 'password123',
            'role' => 'admin',
        ]);

        $this->assertEquals(UserRole::ADMIN, $user->role);
    }

    public function test_creates_user_with_user_role(): void
    {
        $user = $this->action->execute([
            'name' => 'Regular User',
            'email' => 'user@example.com',
            'password' => 'password123',
            'role' => 'user',
        ]);

        $this->assertEquals(UserRole::USER, $user->role);
    }

    public function test_defaults_to_user_role_when_not_specified(): void
    {
        $user = $this->action->execute([
            'name' => 'Test User',
            'email' => 'test@example.com',
            'password' => 'password123',
        ]);

        $this->assertEquals(UserRole::USER, $user->role);
    }

    public function test_defaults_to_user_role_for_invalid_role(): void
    {
        $user = $this->action->execute([
            'name' => 'Test User',
            'email' => 'test@example.com',
            'password' => 'password123',
            'role' => 'invalid',
        ]);

        $this->assertEquals(UserRole::USER, $user->role);
    }
}

<?php

declare(strict_types=1);

namespace App\Actions\Admin;

use App\Enums\UserRole;
use App\Models\User;
use Illuminate\Support\Facades\Hash;

final readonly class CreateUser
{
    /**
     * Create a new user.
     *
     * @param array{name: string, email: string, password: string, role?: string} $data
     */
    public function execute(array $data): User
    {
        return User::create([
            'name' => $data['name'],
            'email' => $data['email'],
            'password' => Hash::make($data['password']),
            'role' => UserRole::tryFrom($data['role'] ?? 'user') ?? UserRole::USER,
        ]);
    }
}

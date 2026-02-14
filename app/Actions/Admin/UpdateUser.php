<?php

declare(strict_types=1);

namespace App\Actions\Admin;

use App\Enums\UserRole;
use App\Models\User;
use Illuminate\Support\Facades\Hash;

final readonly class UpdateUser
{
    /**
     * Update an existing user.
     *
     * @param array{name?: string, email?: string, password?: string, role?: string} $data
     */
    public function execute(User $user, array $data): User
    {
        $updateData = [];

        if (isset($data['name'])) {
            $updateData['name'] = $data['name'];
        }

        if (isset($data['email'])) {
            $updateData['email'] = $data['email'];
        }

        if (isset($data['password'])) {
            $updateData['password'] = Hash::make($data['password']);
        }

        if (isset($data['role'])) {
            $updateData['role'] = UserRole::tryFrom($data['role']) ?? UserRole::USER;
        }

        $user->update($updateData);

        $user->refresh();

        return $user;
    }
}

<?php

declare(strict_types=1);

namespace App\Actions\Auth;

use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Storage;

final readonly class UpdateUserProfile
{
    /**
     * Update a user's profile.
     *
     * @param array{name?: string, preferences?: array<string, mixed>, password?: string} $data
     */
    public function execute(User $user, array $data, ?UploadedFile $avatar = null): User
    {
        if (isset($data['name'])) {
            $user->name = $data['name'];
        }

        if (isset($data['preferences'])) {
            $currentPreferences = $user->preferences ?? [];
            $user->preferences = array_merge($currentPreferences, $data['preferences']);
        }

        // Handle avatar upload - store new avatar first
        $oldAvatarPath = null;
        if ($avatar) {
            $oldAvatarPath = $user->avatar_path;
            $path = $avatar->store('avatars', 'public');

            if ($path === false) {
                throw new \RuntimeException('Failed to upload avatar');
            }

            $user->avatar_path = $path;
        }

        // Handle password change - explicitly hash for defense in depth
        if (isset($data['password'])) {
            $user->password = Hash::make($data['password']);
        }

        $user->save();

        // Delete old avatar only after successful save
        if ($oldAvatarPath) {
            Storage::disk('public')->delete($oldAvatarPath);
        }

        return $user;
    }
}

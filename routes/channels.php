<?php

declare(strict_types=1);

use App\Models\User;
use Illuminate\Support\Facades\Broadcast;

Broadcast::channel('user.{userId}', function (User $user, string $userId): bool {
    return $user->uuid === $userId;
});

Broadcast::channel('companion.{userId}', function (User $user, string $userId): array|false {
    if ($user->uuid !== $userId) {
        return false;
    }

    $isCompanion = request()->hasHeader('X-Companion');

    if ($isCompanion) {
        return [
            'id' => 'companion',
            'type' => 'companion',
            'gamdl_available' => request()->header('X-Companion-Gamdl') === '1',
        ];
    }

    return [
        'id' => 'web',
        'type' => 'web',
    ];
});

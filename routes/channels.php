<?php

declare(strict_types=1);

use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Broadcast;
use Illuminate\Support\Facades\Route;

// Custom broadcasting auth route under auth:sanctum so Bearer tokens work.
// For companion presence subscriptions we use "companion:{uuid}" as the Pusher
// user_id so the companion and web UI appear as distinct members — Pusher only
// fires member_added once per unique user_id, so sharing the same id would
// prevent the web UI from seeing the companion arrive.
Route::post('/broadcasting/auth', function (Request $request) {
    $channel = (string) $request->input('channel_name', '');
    $socketId = (string) $request->input('socket_id', '');

    if (str_starts_with($channel, 'presence-') && $request->hasHeader('X-Companion')) {
        /** @var User $user */
        $user = $request->user();
        $userId = 'companion:' . $user->uuid;
        $gamdlAvailable = $request->header('X-Companion-Gamdl') === '1';

        $channelData = (string) json_encode([
            'user_id'   => $userId,
            'user_info' => [
                'id'              => 'companion',
                'type'            => 'companion',
                'gamdl_available' => $gamdlAvailable,
            ],
        ]);

        $secret    = config('broadcasting.connections.pusher.secret');
        $key       = config('broadcasting.connections.pusher.key');
        $signature = hash_hmac('sha256', "{$socketId}:{$channel}:{$channelData}", $secret);

        return response()->json([
            'auth'         => "{$key}:{$signature}",
            'channel_data' => $channelData,
        ]);
    }

    return Broadcast::auth($request);
})->middleware('auth:sanctum');

Broadcast::channel('user.{userId}', function (User $user, string $userId): bool {
    return $user->uuid === $userId;
});

Broadcast::channel('companion.{userId}', function (User $user, string $userId): array|false {
    if ($user->uuid !== $userId) {
        return false;
    }

    return [
        'id'   => 'web',
        'type' => 'web',
    ];
});

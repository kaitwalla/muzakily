<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\LoginRequest;
use App\Http\Resources\Api\V1\UserResource;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\ValidationException;

class AuthController extends Controller
{
    /**
     * Login and receive API token.
     */
    public function login(LoginRequest $request): JsonResponse
    {
        $user = User::where('email', $request->validated('email'))->first();

        if (!$user || !Hash::check($request->validated('password'), $user->password)) {
            throw ValidationException::withMessages([
                'email' => ['The provided credentials are incorrect.'],
            ]);
        }

        $token = $user->createToken('api-token')->plainTextToken;

        return response()->json([
            'data' => [
                'token' => $token,
                'user' => new UserResource($user),
            ],
        ]);
    }

    /**
     * Logout and revoke current token.
     */
    public function logout(Request $request): JsonResponse
    {
        /** @var \Laravel\Sanctum\PersonalAccessToken|null $token */
        $token = $request->user()?->currentAccessToken();
        $token?->delete();

        return response()->json(null, 204);
    }

    /**
     * Get the authenticated user.
     */
    public function me(Request $request): JsonResponse
    {
        return response()->json([
            'data' => new UserResource($request->user()),
        ]);
    }

    /**
     * Update the authenticated user's profile.
     */
    public function updateProfile(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'name' => ['sometimes', 'string', 'max:255'],
            'preferences' => ['sometimes', 'array'],
            'preferences.audio_quality' => ['sometimes', 'string', 'in:auto,high,normal,low'],
            'preferences.crossfade' => ['sometimes', 'integer', 'in:0,3,5,10'],
        ]);

        /** @var User $user */
        $user = $request->user();

        if (isset($validated['name'])) {
            $user->name = $validated['name'];
        }

        if (isset($validated['preferences'])) {
            $currentPreferences = $user->preferences ?? [];
            $user->preferences = array_merge($currentPreferences, $validated['preferences']);
        }

        $user->save();

        return response()->json([
            'data' => new UserResource($user),
        ]);
    }
}

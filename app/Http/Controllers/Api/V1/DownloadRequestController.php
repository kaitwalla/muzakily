<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1;

use App\Enums\DownloadRequestStatus;
use App\Events\DownloadRequested;
use App\Http\Controllers\Controller;
use App\Models\DownloadRequest;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class DownloadRequestController extends Controller
{
    /**
     * List the authenticated user's recent download requests.
     */
    public function index(Request $request): JsonResponse
    {
        $requests = DownloadRequest::where('user_id', $request->user()->id)
            ->orderByDesc('created_at')
            ->limit(20)
            ->get();

        return response()->json([
            'data' => $requests,
        ]);
    }

    /**
     * Create a new download request and broadcast to companion.
     */
    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'url' => ['required', 'string', 'url'],
            'tag_ids' => ['nullable', 'array'],
            'tag_ids.*' => ['integer', 'exists:tags,id'],
        ]);

        /** @var \App\Models\User $user */
        $user = $request->user();

        $downloadRequest = DownloadRequest::create([
            'user_id' => $user->id,
            'url' => $validated['url'],
            'tag_ids' => $validated['tag_ids'] ?? [],
            'status' => DownloadRequestStatus::PENDING,
            'song_id' => null,
            'error' => null,
        ]);

        DownloadRequested::dispatch($user, $downloadRequest);

        return response()->json([
            'data' => $downloadRequest,
        ], 201);
    }

    /**
     * Show a specific download request (for status polling).
     */
    public function show(Request $request, DownloadRequest $download): JsonResponse
    {
        /** @var \App\Models\User $user */
        $user = $request->user();

        if ($download->user_id !== $user->id) {
            abort(403);
        }

        return response()->json([
            'data' => $download,
        ]);
    }
}

<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1;

use App\Actions\Songs\UploadSong;
use App\Exceptions\UnsupportedAudioFormatException;
use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class UploadController extends Controller
{
    public function __construct(
        private readonly UploadSong $uploadSong,
    ) {}

    /**
     * Upload a song file.
     */
    public function upload(Request $request): JsonResponse
    {
        $this->authorize('upload', \App\Models\Song::class);

        $validated = $request->validate([
            'file' => [
                'required',
                'file',
                'max:102400', // 100MB
                'mimes:mp3,mp4,m4a,flac',
            ],
            'download_request_id' => ['nullable', 'string', 'uuid', 'exists:download_requests,id'],
        ]);

        try {
            $result = $this->uploadSong->execute(
                $request->file('file'),
                $validated['download_request_id'] ?? null,
            );
        } catch (UnsupportedAudioFormatException) {
            return response()->json([
                'error' => [
                    'code' => 'UNSUPPORTED_FORMAT',
                    'message' => 'The provided audio format is not supported.',
                ],
            ], 415);
        }

        return response()->json([
            'data' => $result,
        ], 202);
    }
}

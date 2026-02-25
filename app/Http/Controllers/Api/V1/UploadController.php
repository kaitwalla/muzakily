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

        $request->validate([
            'file' => [
                'required',
                'file',
                'max:102400', // 100MB
                'mimes:mp3,mp4,m4a,flac',
            ],
        ]);

        try {
            $result = $this->uploadSong->execute($request->file('file'));
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

<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1;

use App\Contracts\MusicStorageInterface;
use App\Enums\AudioFormat;
use App\Http\Controllers\Controller;
use App\Http\Resources\Api\V1\SongResource;
use App\Jobs\ProcessUploadedSongJob;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

class UploadController extends Controller
{
    public function __construct(
        private MusicStorageInterface $storage,
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

        $file = $request->file('file');
        $extension = strtolower($file->getClientOriginalExtension());
        $format = AudioFormat::fromExtension($extension);

        if ($format === null) {
            return response()->json([
                'error' => [
                    'code' => 'UNSUPPORTED_FORMAT',
                    'message' => 'The uploaded file format is not supported.',
                ],
            ], 415);
        }

        // Generate storage path
        $uuid = (string) Str::uuid();
        $storagePath = sprintf('uploads/%s/%s.%s', date('Y/m'), $uuid, $extension);

        // Store temporarily then upload to R2
        $tempPath = $file->store('temp');
        $fullTempPath = storage_path('app/' . $tempPath);

        try {
            $this->storage->upload($storagePath, $fullTempPath);
        } finally {
            @unlink($fullTempPath);
        }

        // Dispatch processing job
        ProcessUploadedSongJob::dispatch($storagePath, $file->getClientOriginalName());

        return response()->json([
            'data' => [
                'job_id' => $uuid,
                'status' => 'processing',
                'filename' => $file->getClientOriginalName(),
            ],
        ], 202);
    }
}

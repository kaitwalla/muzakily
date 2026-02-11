<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1;

use App\Contracts\MusicStorageInterface;
use App\Http\Controllers\Controller;
use App\Models\Song;
use App\Services\Streaming\TranscodingService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class StreamController extends Controller
{
    public function __construct(
        private MusicStorageInterface $storage,
        private TranscodingService $transcodingService,
    ) {}

    /**
     * Stream a song (return presigned URL in JSON).
     */
    public function stream(Request $request, Song $song): JsonResponse
    {
        $format = $request->input('format', 'original');
        $bitrate = (int) $request->input('bitrate', config('muzakily.transcoding.default_bitrate', 256));

        // Get stream URL (may trigger transcoding if needed)
        $url = $this->transcodingService->getStreamUrl($song, $format, $bitrate);

        return response()->json([
            'data' => [
                'url' => $url,
                'audio_format' => $song->audio_format->value,
                'audio_length' => $song->length,
            ],
        ]);
    }

    /**
     * Download a song (redirect to stream URL).
     */
    public function download(Song $song): RedirectResponse
    {
        $url = $this->storage->getStreamUrl(
            $song->storage_path,
            config('muzakily.r2.presigned_expiry', 3600)
        );

        return redirect()->away($url);
    }
}

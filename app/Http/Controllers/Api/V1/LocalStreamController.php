<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Services\Storage\LocalStorageService;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\BinaryFileResponse;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\StreamedResponse;

class LocalStreamController extends Controller
{
    public function __construct(
        private LocalStorageService $localStorage,
    ) {}

    /**
     * Stream a file from local storage.
     * Supports Range requests for seeking.
     */
    public function stream(Request $request): Response
    {
        // Validate signed URL
        if (!$request->hasValidSignature()) {
            abort(403, 'Invalid or expired signature');
        }

        $path = $request->query('path');

        if (!is_string($path) || empty($path)) {
            abort(400, 'Missing path parameter');
        }

        if (!$this->localStorage->exists($path)) {
            abort(404, 'File not found');
        }

        $fullPath = $this->localStorage->getPath($path);
        $mimeType = $this->getMimeType($path);
        $fileSize = filesize($fullPath);

        if ($fileSize === false) {
            abort(500, 'Unable to determine file size');
        }

        // Handle Range requests for seeking
        $rangeHeader = $request->header('Range');

        if ($rangeHeader !== null) {
            return $this->handleRangeRequest($fullPath, $mimeType, $fileSize, $rangeHeader);
        }

        // Return full file with streaming support
        return new BinaryFileResponse($fullPath, 200, [
            'Content-Type' => $mimeType,
            'Accept-Ranges' => 'bytes',
            'Content-Length' => $fileSize,
        ]);
    }

    /**
     * Handle HTTP Range requests for partial content.
     */
    private function handleRangeRequest(
        string $fullPath,
        string $mimeType,
        int $fileSize,
        string $rangeHeader
    ): StreamedResponse {
        // Parse range header (e.g., "bytes=0-1023")
        preg_match('/bytes=(\d+)-(\d*)/', $rangeHeader, $matches);

        $start = (int) $matches[1];
        $end = !empty($matches[2]) ? (int) $matches[2] : $fileSize - 1;

        // Validate range
        if ($start > $end || $start >= $fileSize) {
            abort(416, 'Range Not Satisfiable');
        }

        // Clamp end to file size
        $end = min($end, $fileSize - 1);
        $length = $end - $start + 1;

        $response = new StreamedResponse(function () use ($fullPath, $start, $length): void {
            $handle = fopen($fullPath, 'rb');

            if ($handle === false) {
                return;
            }

            fseek($handle, $start);

            $remaining = $length;
            $chunkSize = 8192;

            while ($remaining > 0 && !feof($handle)) {
                $readSize = min($chunkSize, $remaining);
                $chunk = fread($handle, $readSize);

                if ($chunk === false) {
                    break;
                }

                echo $chunk;
                flush();

                $remaining -= strlen($chunk);
            }

            fclose($handle);
        }, 206);

        $response->headers->set('Content-Type', $mimeType);
        $response->headers->set('Accept-Ranges', 'bytes');
        $response->headers->set('Content-Range', "bytes {$start}-{$end}/{$fileSize}");
        $response->headers->set('Content-Length', (string) $length);

        return $response;
    }

    /**
     * Get MIME type from file extension.
     */
    private function getMimeType(string $path): string
    {
        $extension = strtolower(pathinfo($path, PATHINFO_EXTENSION));

        return match ($extension) {
            'mp3' => 'audio/mpeg',
            'm4a', 'aac' => 'audio/mp4',
            'flac' => 'audio/flac',
            'wav' => 'audio/wav',
            'ogg' => 'audio/ogg',
            default => 'application/octet-stream',
        };
    }
}

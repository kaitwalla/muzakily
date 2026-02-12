<?php

declare(strict_types=1);

namespace App\Services\Storage;

use App\Contracts\MusicStorageInterface;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\URL;

class LocalStorageService implements MusicStorageInterface
{
    /**
     * Upload a file to local storage.
     */
    public function upload(string $key, string $localPath): bool
    {
        $contents = file_get_contents($localPath);

        if ($contents === false) {
            return false;
        }

        return Storage::disk('music')->put($key, $contents);
    }

    /**
     * Download a file from local storage to a path.
     */
    public function download(string $key, string $localPath): bool
    {
        $contents = Storage::disk('music')->get($key);

        if ($contents === null) {
            return false;
        }

        return (bool) file_put_contents($localPath, $contents);
    }

    /**
     * Delete a file from local storage.
     */
    public function delete(string $key): bool
    {
        return Storage::disk('music')->delete($key);
    }

    /**
     * Check if a file exists in local storage.
     */
    public function exists(string $key): bool
    {
        return Storage::disk('music')->exists($key);
    }

    /**
     * Get a signed URL for streaming the file.
     */
    public function getStreamUrl(string $key, int $expiry = 3600): string
    {
        return URL::temporarySignedRoute(
            'stream.local',
            now()->addSeconds($expiry),
            ['path' => $key]
        );
    }

    /**
     * Get file metadata.
     *
     * @return array{size: int, last_modified: \DateTimeInterface|null, etag: string|null}|null
     */
    public function getMetadata(string $key): ?array
    {
        if (!$this->exists($key)) {
            return null;
        }

        $disk = Storage::disk('music');
        $size = $disk->size($key);
        $lastModified = $disk->lastModified($key);

        // Generate an ETag from file hash (md5 of first 8KB for performance)
        $path = $disk->path($key);
        $handle = fopen($path, 'rb');
        $etag = null;

        if ($handle !== false) {
            $chunk = fread($handle, 8192);
            fclose($handle);

            if ($chunk !== false) {
                $etag = md5($chunk . $size);
            }
        }

        return [
            'size' => $size,
            'last_modified' => $lastModified ? \DateTimeImmutable::createFromFormat('U', (string) $lastModified) ?: null : null,
            'etag' => $etag,
        ];
    }

    /**
     * Get the full path to a file.
     */
    public function getPath(string $key): string
    {
        return Storage::disk('music')->path($key);
    }

    /**
     * List all files in the music storage.
     *
     * @return \Generator<array{key: string, size: int, last_modified: \DateTimeInterface, etag: string}>
     */
    public function listObjects(?string $prefix = null): \Generator
    {
        $disk = Storage::disk('music');
        $files = $disk->allFiles($prefix ?? '');

        foreach ($files as $file) {
            $metadata = $this->getMetadata($file);

            if ($metadata === null) {
                continue;
            }

            yield [
                'key' => $file,
                'size' => $metadata['size'],
                'last_modified' => $metadata['last_modified'] ?? new \DateTimeImmutable(),
                'etag' => $metadata['etag'] ?? md5($file),
            ];
        }
    }

    /**
     * Download partial content of a file (for local files, just return full content).
     *
     * @return array{header: string, footer: string, file_size: int}|null
     */
    public function downloadPartial(
        string $key,
        int $headerSize = 524288,
        int $footerSize = 131072
    ): ?array {
        $path = $this->getPath($key);

        if (!file_exists($path)) {
            return null;
        }

        $fileSize = filesize($path);

        if ($fileSize === false) {
            return null;
        }

        $handle = fopen($path, 'rb');

        if ($handle === false) {
            return null;
        }

        try {
            // If file is smaller than header + footer, read entire file
            if ($fileSize <= $headerSize + $footerSize) {
                $content = fread($handle, $fileSize);

                return [
                    'header' => $content !== false ? $content : '',
                    'footer' => '',
                    'file_size' => $fileSize,
                ];
            }

            // Read header
            $header = fread($handle, $headerSize);

            // Seek to footer position and read
            fseek($handle, -$footerSize, SEEK_END);
            $footer = fread($handle, $footerSize);

            return [
                'header' => $header !== false ? $header : '',
                'footer' => $footer !== false ? $footer : '',
                'file_size' => $fileSize,
            ];
        } finally {
            fclose($handle);
        }
    }

    /**
     * Create a temporary file with header and footer content for metadata extraction.
     *
     * @return string|false Path to the temp file, or false on failure
     */
    public function createPartialTempFile(
        string $headerContent,
        string $footerContent,
        int $fileSize
    ): string|false {
        $tempPath = tempnam(sys_get_temp_dir(), 'muzakily_partial_');

        if ($tempPath === false) {
            return false;
        }

        $handle = fopen($tempPath, 'wb');

        if ($handle === false) {
            @unlink($tempPath);
            return false;
        }

        try {
            fwrite($handle, $headerContent);

            $gapSize = $fileSize - strlen($headerContent) - strlen($footerContent);

            if ($gapSize < 0) {
                ftruncate($handle, $fileSize);
                return $tempPath;
            }

            if ($gapSize > 0) {
                fseek($handle, strlen($headerContent) + $gapSize);
            }

            fwrite($handle, $footerContent);
            ftruncate($handle, $fileSize);

            return $tempPath;
        } finally {
            fclose($handle);
        }
    }
}

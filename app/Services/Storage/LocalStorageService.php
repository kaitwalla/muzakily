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
}

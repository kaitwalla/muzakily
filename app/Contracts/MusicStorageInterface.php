<?php

declare(strict_types=1);

namespace App\Contracts;

interface MusicStorageInterface
{
    /**
     * Upload a file to storage.
     */
    public function upload(string $key, string $localPath): bool;

    /**
     * Download a file from storage to a local path.
     */
    public function download(string $key, string $localPath): bool;

    /**
     * Delete a file from storage.
     */
    public function delete(string $key): bool;

    /**
     * Check if a file exists in storage.
     */
    public function exists(string $key): bool;

    /**
     * Get a URL for streaming the file.
     */
    public function getStreamUrl(string $key, int $expiry = 3600): string;

    /**
     * Get file metadata.
     *
     * @return array{size: int, last_modified: \DateTimeInterface|null, etag: string|null}|null
     */
    public function getMetadata(string $key): ?array;
}

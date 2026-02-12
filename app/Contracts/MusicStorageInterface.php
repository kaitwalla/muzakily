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

    /**
     * Get the local filesystem path for a file (if available).
     * Returns null for remote storage like R2/S3.
     */
    public function getLocalPath(string $key): ?string;

    /**
     * List all files in storage.
     *
     * @return \Generator<array{key: string, size: int, last_modified: \DateTimeInterface, etag: string}>
     */
    public function listObjects(?string $prefix = null): \Generator;

    /**
     * Download partial content of a file (header + footer).
     *
     * @return array{header: string, footer: string, file_size: int}|null
     */
    public function downloadPartial(
        string $key,
        int $headerSize = 524288,
        int $footerSize = 131072
    ): ?array;

    /**
     * Create a temporary file with header and footer content for metadata extraction.
     *
     * @return string|false Path to the temp file, or false on failure
     */
    public function createPartialTempFile(
        string $headerContent,
        string $footerContent,
        int $fileSize
    ): string|false;
}

<?php

declare(strict_types=1);

namespace App\Services\Storage;

use App\Contracts\MusicStorageInterface;
use Aws\S3\S3Client;
use Illuminate\Support\Facades\Storage;

class R2StorageService implements MusicStorageInterface
{
    private S3Client $client;
    private string $bucket;

    public function __construct()
    {
        /** @var array{key: string, secret: string, region: string, bucket: string, endpoint: string, use_path_style_endpoint?: bool} $config */
        $config = config('filesystems.disks.r2');

        $this->client = new S3Client([
            'version' => 'latest',
            'region' => $config['region'],
            'endpoint' => $config['endpoint'],
            'use_path_style_endpoint' => $config['use_path_style_endpoint'] ?? false,
            'credentials' => [
                'key' => $config['key'],
                'secret' => $config['secret'],
            ],
        ]);
        $this->bucket = $config['bucket'];
    }

    /**
     * Get a presigned URL for an object.
     */
    public function getPresignedUrl(string $key, int $expiry = 3600, string $disposition = 'inline'): string
    {
        $cmd = $this->client->getCommand('GetObject', [
            'Bucket' => $this->bucket,
            'Key' => $key,
            'ResponseContentDisposition' => $disposition,
        ]);

        return (string) $this->client->createPresignedRequest($cmd, "+{$expiry} seconds")->getUri();
    }

    /**
     * Get a URL for streaming the file.
     */
    public function getStreamUrl(string $key, int $expiry = 3600): string
    {
        return $this->getPresignedUrl($key, $expiry, 'inline');
    }

    /**
     * Get a URL for downloading the file.
     */
    public function getDownloadUrl(string $key, int $expiry = 3600, ?string $filename = null): string
    {
        $disposition = 'attachment';
        if ($filename !== null) {
            $disposition .= "; filename=\"{$filename}\"";
        }
        return $this->getPresignedUrl($key, $expiry, $disposition);
    }

    /**
     * Upload a file to R2.
     */
    public function upload(string $key, string $localPath): bool
    {
        return Storage::disk('r2')->put($key, file_get_contents($localPath));
    }

    /**
     * Download a file from R2 to local storage.
     */
    public function download(string $key, string $localPath): bool
    {
        $contents = Storage::disk('r2')->get($key);

        if ($contents === null) {
            return false;
        }

        return (bool) file_put_contents($localPath, $contents);
    }

    /**
     * Check if a file exists in R2.
     */
    public function exists(string $key): bool
    {
        return Storage::disk('r2')->exists($key);
    }

    /**
     * Delete a file from R2.
     */
    public function delete(string $key): bool
    {
        return Storage::disk('r2')->delete($key);
    }

    /**
     * Get file metadata from R2.
     *
     * @return array{size: int, last_modified: \DateTimeInterface|null, etag: string|null}|null
     */
    public function getMetadata(string $key): ?array
    {
        try {
            $result = $this->client->headObject([
                'Bucket' => $this->bucket,
                'Key' => $key,
            ]);

            return [
                'size' => (int) $result['ContentLength'],
                'last_modified' => $result['LastModified'],
                'etag' => trim($result['ETag'] ?? '', '"'),
            ];
        } catch (\Exception $e) {
            return null;
        }
    }

    /**
     * Get the local filesystem path for a file.
     * R2 is remote storage, so this always returns null.
     */
    public function getLocalPath(string $key): ?string
    {
        return null;
    }

    /**
     * List objects in a bucket with optional prefix.
     *
     * @return \Generator<array{key: string, size: int, last_modified: \DateTimeInterface, etag: string}>
     */
    public function listObjects(?string $prefix = null): \Generator
    {
        $params = [
            'Bucket' => $this->bucket,
        ];

        if ($prefix !== null) {
            $params['Prefix'] = $prefix;
        }

        $paginator = $this->client->getPaginator('ListObjectsV2', $params);

        foreach ($paginator as $result) {
            foreach ($result['Contents'] ?? [] as $object) {
                yield [
                    'key' => $object['Key'],
                    'size' => (int) $object['Size'],
                    'last_modified' => $object['LastModified'],
                    'etag' => trim($object['ETag'] ?? '', '"'),
                ];
            }
        }
    }

    /**
     * Download partial content of a file using HTTP Range requests.
     *
     * This fetches only the header and footer of a file, which is sufficient
     * for extracting metadata from audio files without downloading the entire file.
     *
     * @param string $key The S3 object key
     * @param int $headerSize Bytes to fetch from the start (default 512KB)
     * @param int $footerSize Bytes to fetch from the end (default 128KB)
     * @return array{header: string, footer: string, file_size: int}|null
     */
    public function downloadPartial(
        string $key,
        int $headerSize = 524288,
        int $footerSize = 131072
    ): ?array {
        try {
            // Get file size first
            $headResult = $this->client->headObject([
                'Bucket' => $this->bucket,
                'Key' => $key,
            ]);

            $fileSize = (int) $headResult['ContentLength'];

            // If file is smaller than header + footer, download entire file
            if ($fileSize <= $headerSize + $footerSize) {
                $result = $this->client->getObject([
                    'Bucket' => $this->bucket,
                    'Key' => $key,
                ]);

                return [
                    'header' => (string) $result['Body'],
                    'footer' => '',
                    'file_size' => $fileSize,
                ];
            }

            // Fetch header (first N bytes)
            $headerResult = $this->client->getObject([
                'Bucket' => $this->bucket,
                'Key' => $key,
                'Range' => 'bytes=0-' . ($headerSize - 1),
            ]);

            // Fetch footer (last N bytes)
            $footerStart = $fileSize - $footerSize;
            $footerResult = $this->client->getObject([
                'Bucket' => $this->bucket,
                'Key' => $key,
                'Range' => 'bytes=' . $footerStart . '-' . ($fileSize - 1),
            ]);

            return [
                'header' => (string) $headerResult['Body'],
                'footer' => (string) $footerResult['Body'],
                'file_size' => $fileSize,
            ];
        } catch (\Exception $e) {
            report($e);
            return null;
        }
    }

    /**
     * Create a temporary file with header and footer content for metadata extraction.
     *
     * This creates a file that has the header at the beginning, zeros in the middle,
     * and the footer at the end, maintaining the correct file size for metadata tools.
     *
     * @param string $headerContent Content from the file header
     * @param string $footerContent Content from the file footer
     * @param int $fileSize Total size of the original file
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
            // Write header
            fwrite($handle, $headerContent);

            // Calculate gap size (the part we didn't download)
            $gapSize = $fileSize - strlen($headerContent) - strlen($footerContent);

            // Guard against negative gap size (header + footer > fileSize)
            // This can happen with small files or inconsistent data
            if ($gapSize < 0) {
                // File is smaller than header + footer combined
                // Just write header up to fileSize (footer already included in header for small files)
                ftruncate($handle, $fileSize);
                return $tempPath;
            }

            if ($gapSize > 0) {
                // Seek to the footer position and write zeros to extend the file
                fseek($handle, strlen($headerContent) + $gapSize);
            }

            // Write footer
            fwrite($handle, $footerContent);

            // Ensure file is the correct size
            ftruncate($handle, $fileSize);

            return $tempPath;
        } finally {
            fclose($handle);
        }
    }
}

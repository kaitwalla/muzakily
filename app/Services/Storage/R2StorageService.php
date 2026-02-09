<?php

declare(strict_types=1);

namespace App\Services\Storage;

use Aws\S3\S3Client;
use Illuminate\Support\Facades\Storage;

class R2StorageService
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
}

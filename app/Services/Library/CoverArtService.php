<?php

declare(strict_types=1);

namespace App\Services\Library;

use App\Models\Album;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;

class CoverArtService
{
    private const COVERS_PREFIX = 'covers';

    /**
     * Store cover art for an album and return the public URL.
     *
     * @param array<string, mixed> $coverArt Expected keys: 'data' (string), 'mime_type' (string)
     */
    public function storeForAlbum(Album $album, array $coverArt): ?string
    {
        // Validate required keys exist and are strings
        if (!isset($coverArt['mime_type'], $coverArt['data']) ||
            !is_string($coverArt['mime_type']) ||
            !is_string($coverArt['data'])) {
            return null;
        }

        $extension = $this->getExtension($coverArt['mime_type']);
        if ($extension === null) {
            return null;
        }

        $filename = sprintf('%s/%s.%s', self::COVERS_PREFIX, $album->uuid, $extension);

        $disk = $this->getDisk();

        try {
            $disk->put($filename, $coverArt['data'], 'public');

            return $this->getPublicUrl($filename);
        } catch (\Throwable $e) {
            report($e);
            return null;
        }
    }

    /**
     * Download an image from a URL and store it for an album.
     */
    public function storeFromUrl(Album $album, string $url): ?string
    {
        if (!$this->isAllowedUrl($url)) {
            return null;
        }

        try {
            $response = Http::timeout(30)->get($url);

            if (!$response->successful()) {
                return null;
            }

            $contentType = $response->header('Content-Type') ?: 'image/jpeg';
            // Handle content types like "image/jpeg; charset=utf-8"
            $mimeType = explode(';', $contentType)[0];

            return $this->storeForAlbum($album, [
                'data' => $response->body(),
                'mime_type' => trim($mimeType),
            ]);
        } catch (\Throwable $e) {
            report($e);
            return null;
        }
    }

    /**
     * Check if a cover URL is external (not stored in our storage).
     */
    public function isExternalUrl(?string $url): bool
    {
        if ($url === null || $url === '') {
            return false;
        }

        return str_starts_with($url, 'http://') || str_starts_with($url, 'https://');
    }

    /**
     * Check if an album already has stored cover art.
     */
    public function hasStoredCover(Album $album): bool
    {
        $disk = $this->getDisk();
        $prefix = self::COVERS_PREFIX . '/' . $album->uuid;

        foreach (['jpg', 'jpeg', 'png', 'gif', 'webp'] as $ext) {
            if ($disk->exists($prefix . '.' . $ext)) {
                return true;
            }
        }

        return false;
    }

    /**
     * Get the storage disk based on configuration.
     */
    private function getDisk(): \Illuminate\Contracts\Filesystem\Filesystem
    {
        $driver = config('muzakily.storage.driver', 'r2');

        return Storage::disk($driver === 'local' ? 'public' : 'r2');
    }

    /**
     * Get the public URL for a stored cover.
     */
    private function getPublicUrl(string $filename): string
    {
        $driver = config('muzakily.storage.driver', 'r2');

        if ($driver === 'local') {
            return url('storage/' . $filename);
        }

        // R2 public URL
        $r2Url = config('muzakily.r2.url');
        if ($r2Url) {
            return rtrim($r2Url, '/') . '/' . $filename;
        }

        // Fallback to generating a URL from the disk
        return Storage::disk('r2')->url($filename);
    }

    /**
     * Get file extension from MIME type.
     */
    private function getExtension(string $mimeType): ?string
    {
        return match ($mimeType) {
            'image/jpeg', 'image/jpg' => 'jpg',
            'image/png' => 'png',
            'image/gif' => 'gif',
            'image/webp' => 'webp',
            default => null,
        };
    }

    /**
     * Validate that a URL is allowed for fetching (prevents SSRF).
     */
    private function isAllowedUrl(string $url): bool
    {
        $parsed = parse_url($url);

        if (!isset($parsed['host']) || !isset($parsed['scheme'])) {
            return false;
        }

        // Only allow HTTPS (and HTTP for local dev)
        if (!in_array($parsed['scheme'], ['http', 'https'], true)) {
            return false;
        }

        $host = $parsed['host'];

        // Block localhost and loopback addresses
        if (in_array($host, ['localhost', '127.0.0.1', '::1'], true)) {
            return false;
        }

        // Resolve hostname to IP and block private/reserved ranges
        $ip = gethostbyname($host);
        if ($ip !== $host && filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE) === false) {
            return false;
        }

        return true;
    }
}

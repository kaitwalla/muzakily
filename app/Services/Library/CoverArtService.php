<?php

declare(strict_types=1);

namespace App\Services\Library;

use App\Models\Album;
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
}

<?php

declare(strict_types=1);

namespace App\Services\Metadata;

use App\Models\Artist;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;

class ArtistImageService
{
    private const IMAGES_PREFIX = 'artists';

    public function __construct(
        private DeezerService $deezer,
        private TheAudioDbService $theAudioDb,
        private FanartTvService $fanartTv,
    ) {}

    /**
     * Fetch and cache artist image, trying multiple sources.
     * Order: Deezer -> TheAudioDB -> Fanart.tv (requires MusicBrainz ID)
     *
     * @param bool $force Re-fetch even if artist already has an image
     */
    public function fetchAndStore(Artist $artist, bool $force = false): ?string
    {
        // Skip if already has an image and not forcing
        if (!$force && $artist->image) {
            return $artist->image;
        }

        // Use a cache lock to prevent concurrent fetches for the same artist
        return Cache::lock("artist-image-fetch-{$artist->id}", 120)->get(function () use ($artist, $force) {
            // Re-check after acquiring lock
            $artist->refresh();
            if (!$force && $artist->image) {
                return $artist->image;
            }

            $imageUrl = $this->findImage($artist);

            if (!$imageUrl) {
                return null;
            }

            // Download and cache the image (this will replace any existing image atomically)
            return $this->cacheImage($artist, $imageUrl);
        });
    }

    /**
     * Find an image URL from available sources.
     * Each service call is wrapped in try/catch to continue on failure.
     */
    private function findImage(Artist $artist): ?string
    {
        // Try Deezer first (no API key required, good coverage)
        try {
            $url = $this->deezer->getArtistImage($artist->name);
            if ($url) {
                return $url;
            }
        } catch (\Throwable $e) {
            report($e);
        }

        // Try TheAudioDB second
        try {
            $url = $this->theAudioDb->getArtistImage($artist->name);
            if ($url) {
                return $url;
            }
        } catch (\Throwable $e) {
            report($e);
        }

        // Try Fanart.tv if we have a MusicBrainz ID
        if ($artist->musicbrainz_id) {
            try {
                $url = $this->fanartTv->getArtistImage($artist->musicbrainz_id);
                if ($url) {
                    return $url;
                }
            } catch (\Throwable $e) {
                report($e);
            }
        }

        return null;
    }

    /**
     * Download and cache an image locally.
     */
    private function cacheImage(Artist $artist, string $imageUrl): ?string
    {
        try {
            $response = Http::timeout(30)->get($imageUrl);

            if (!$response->successful()) {
                return null;
            }

            $contentType = $response->header('Content-Type') ?: 'image/jpeg';
            $extension = $this->getExtension($contentType);

            $filename = sprintf('%s/%s.%s', self::IMAGES_PREFIX, $artist->uuid, $extension);

            $disk = $this->getDisk();
            $disk->put($filename, $response->body(), 'public');

            $publicUrl = $this->getPublicUrl($filename);

            // Update artist with cached URL (atomic update)
            $artist->update(['image' => $publicUrl]);

            return $publicUrl;
        } catch (\Throwable $e) {
            report($e);
            return null;
        }
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
     * Get the public URL for a stored image.
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

        return Storage::disk('r2')->url($filename);
    }

    /**
     * Get file extension from content type.
     */
    private function getExtension(string $contentType): string
    {
        return match (true) {
            str_contains($contentType, 'png') => 'png',
            str_contains($contentType, 'gif') => 'gif',
            str_contains($contentType, 'webp') => 'webp',
            default => 'jpg',
        };
    }
}

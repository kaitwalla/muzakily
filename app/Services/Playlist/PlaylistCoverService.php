<?php

declare(strict_types=1);

namespace App\Services\Playlist;

use App\Models\Playlist;
use App\Services\Metadata\UnsplashService;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class PlaylistCoverService
{
    private const STORAGE_PATH = 'playlist-covers';

    public function __construct(
        private readonly UnsplashService $unsplashService,
    ) {}

    /**
     * Fetch a random cover image from Unsplash and store it for the playlist.
     *
     * @param Playlist $playlist The playlist to update
     * @param bool $force If true, replace existing cover; if false, skip if cover exists
     * @return bool True if cover was updated, false otherwise
     */
    public function fetchAndStore(Playlist $playlist, bool $force = false): bool
    {
        // Skip if cover already exists and not forcing
        if (!$force && $playlist->cover !== null) {
            return false;
        }

        // Use a lock to prevent concurrent fetches for the same playlist
        $lockKey = 'playlist-cover:' . $playlist->id;
        $lock = Cache::lock($lockKey, 30);

        if (!$lock->get()) {
            return false;
        }

        try {
            // Re-check after acquiring lock in case another process already set the cover
            $playlist->refresh();
            if (!$force && $playlist->cover !== null) {
                return false;
            }

            return $this->performFetchAndStore($playlist);
        } finally {
            $lock->release();
        }
    }

    /**
     * Perform the actual fetch and store operation.
     */
    private function performFetchAndStore(Playlist $playlist): bool
    {
        // Get random photo from Unsplash
        $photo = $this->unsplashService->getRandomPhoto();
        if ($photo === null) {
            return false;
        }

        // Get the regular-sized URL (1080px wide)
        $imageUrl = $photo['urls']['regular'] ?? $photo['urls']['full'] ?? null;
        if ($imageUrl === null) {
            return false;
        }

        // Download the image
        $imageContent = $this->downloadImage($imageUrl);
        if ($imageContent === null) {
            return false;
        }

        // Track the download as required by Unsplash ToS
        $downloadLocation = $photo['links']['download_location'] ?? null;
        if ($downloadLocation !== null) {
            $this->unsplashService->trackDownload($downloadLocation);
        }

        // Determine file extension from URL or default to jpg
        $extension = $this->getExtensionFromUrl($imageUrl);

        // Generate a unique filename
        $filename = self::STORAGE_PATH . '/' . $playlist->id . '-' . Str::random(8) . '.' . $extension;

        // Store the new cover first
        Storage::disk('public')->put($filename, $imageContent);

        // Delete old cover only after new one is successfully stored
        $this->deleteOldCover($playlist);

        // Update playlist
        $playlist->cover = $filename;
        $playlist->save();

        return true;
    }

    /**
     * Download an image from a URL.
     */
    private function downloadImage(string $url): ?string
    {
        try {
            $response = Http::timeout(30)->get($url);

            if (!$response->successful()) {
                return null;
            }

            return $response->body();
        } catch (\Throwable $e) {
            report($e);
            return null;
        }
    }

    /**
     * Extract file extension from URL.
     */
    private function getExtensionFromUrl(string $url): string
    {
        // Parse the URL path
        $path = parse_url($url, PHP_URL_PATH);

        if ($path !== null && $path !== false) {
            $pathInfo = pathinfo($path);
            $ext = strtolower($pathInfo['extension'] ?? '');

            if (in_array($ext, ['jpg', 'jpeg', 'png', 'webp', 'gif'], true)) {
                return $ext === 'jpeg' ? 'jpg' : $ext;
            }
        }

        // Default to jpg
        return 'jpg';
    }

    /**
     * Upload a custom cover image for a playlist.
     *
     * @param Playlist $playlist The playlist to update
     * @param UploadedFile $file The uploaded image file
     * @return bool True if cover was uploaded successfully
     */
    public function uploadCover(Playlist $playlist, UploadedFile $file): bool
    {
        // Get and validate file extension
        $extension = strtolower($file->getClientOriginalExtension() ?: 'jpg');
        if ($extension === 'jpeg') {
            $extension = 'jpg';
        }

        // Validate extension against allowlist
        if (!in_array($extension, ['jpg', 'png', 'webp', 'gif'], true)) {
            $extension = 'jpg';
        }

        // Generate a unique filename
        $filename = self::STORAGE_PATH . '/' . $playlist->id . '-' . Str::random(8) . '.' . $extension;

        // Store the file first
        Storage::disk('public')->put($filename, $file->getContent());

        // Delete old cover only after new one is successfully stored
        $this->deleteOldCover($playlist);

        // Update playlist
        $playlist->cover = $filename;
        $playlist->save();

        return true;
    }

    /**
     * Delete the old cover file if it exists.
     */
    private function deleteOldCover(Playlist $playlist): void
    {
        if ($playlist->cover !== null && Storage::disk('public')->exists($playlist->cover)) {
            Storage::disk('public')->delete($playlist->cover);
        }
    }
}

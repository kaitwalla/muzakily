<?php

declare(strict_types=1);

namespace App\Services\Metadata;

use Illuminate\Support\Facades\Http;

class TheAudioDbService
{
    private const BASE_URL = 'https://www.theaudiodb.com/api/v1/json';
    private const USER_AGENT = 'Muzakily/1.0';

    /**
     * Search for an artist and return their image URL.
     * Uses the free API key "2" for development/testing.
     */
    public function getArtistImage(string $artistName): ?string
    {
        $apiKey = config('muzakily.metadata.theaudiodb.api_key', '2');

        try {
            $response = Http::withUserAgent(self::USER_AGENT)
                ->timeout(10)
                ->get(self::BASE_URL . '/' . $apiKey . '/search.php', [
                    's' => $artistName,
                ]);

            if (!$response->successful()) {
                return null;
            }

            $data = $response->json();
            $artists = $data['artists'] ?? [];

            if (empty($artists) || $artists[0] === null) {
                return null;
            }

            $artist = $artists[0];

            // Prefer thumb, then fanart, then banner
            // Use explicit empty check since API returns "" instead of null for missing images
            $images = [
                $artist['strArtistThumb'] ?? null,
                $artist['strArtistFanart'] ?? null,
                $artist['strArtistBanner'] ?? null,
            ];

            foreach ($images as $image) {
                if (!empty($image)) {
                    return $image;
                }
            }

            return null;
        } catch (\Throwable $e) {
            report($e);
            return null;
        }
    }

    /**
     * Get artist bio/description.
     */
    public function getArtistBio(string $artistName): ?string
    {
        $apiKey = config('muzakily.metadata.theaudiodb.api_key', '2');

        try {
            $response = Http::withUserAgent(self::USER_AGENT)
                ->timeout(10)
                ->get(self::BASE_URL . '/' . $apiKey . '/search.php', [
                    's' => $artistName,
                ]);

            if (!$response->successful()) {
                return null;
            }

            $data = $response->json();
            $artists = $data['artists'] ?? [];

            if (empty($artists) || $artists[0] === null) {
                return null;
            }

            return $artists[0]['strBiographyEN'] ?? null;
        } catch (\Throwable $e) {
            report($e);
            return null;
        }
    }
}

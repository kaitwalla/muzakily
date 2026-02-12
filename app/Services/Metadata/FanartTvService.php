<?php

declare(strict_types=1);

namespace App\Services\Metadata;

use Illuminate\Support\Facades\Http;

class FanartTvService
{
    private const BASE_URL = 'https://webservice.fanart.tv/v3/music';
    private const USER_AGENT = 'Muzakily/1.0';

    /**
     * Get artist image by MusicBrainz ID.
     * Requires a Fanart.tv API key.
     */
    public function getArtistImage(string $musicbrainzId): ?string
    {
        $apiKey = config('muzakily.metadata.fanarttv.api_key');

        if (!$apiKey) {
            return null;
        }

        try {
            $response = Http::withUserAgent(self::USER_AGENT)
                ->timeout(10)
                ->get(self::BASE_URL . '/' . $musicbrainzId, [
                    'api_key' => $apiKey,
                ]);

            if (!$response->successful()) {
                return null;
            }

            $data = $response->json();

            // Priority: artistthumb > artistbackground
            if (!empty($data['artistthumb'])) {
                return $data['artistthumb'][0]['url'] ?? null;
            }

            if (!empty($data['artistbackground'])) {
                return $data['artistbackground'][0]['url'] ?? null;
            }

            return null;
        } catch (\Throwable $e) {
            report($e);
            return null;
        }
    }

    /**
     * Get artist background image by MusicBrainz ID.
     */
    public function getArtistBackground(string $musicbrainzId): ?string
    {
        $apiKey = config('muzakily.metadata.fanarttv.api_key');

        if (!$apiKey) {
            return null;
        }

        try {
            $response = Http::withUserAgent(self::USER_AGENT)
                ->timeout(10)
                ->get(self::BASE_URL . '/' . $musicbrainzId, [
                    'api_key' => $apiKey,
                ]);

            if (!$response->successful()) {
                return null;
            }

            $data = $response->json();

            if (!empty($data['artistbackground'])) {
                return $data['artistbackground'][0]['url'] ?? null;
            }

            return null;
        } catch (\Throwable $e) {
            report($e);
            return null;
        }
    }
}

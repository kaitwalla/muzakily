<?php

declare(strict_types=1);

namespace App\Services\Metadata;

use Illuminate\Support\Facades\Http;

class DeezerService
{
    private const BASE_URL = 'https://api.deezer.com';
    private const USER_AGENT = 'Muzakily/1.0';

    /**
     * Search for an artist and return their image URL.
     */
    public function getArtistImage(string $artistName): ?string
    {
        try {
            $response = Http::withUserAgent(self::USER_AGENT)
                ->timeout(10)
                ->get(self::BASE_URL . '/search/artist', [
                    'q' => $artistName,
                    'limit' => 1,
                ]);

            if (!$response->successful()) {
                return null;
            }

            $data = $response->json();
            $artists = $data['data'] ?? [];

            if (empty($artists)) {
                return null;
            }

            // Return the large picture (500x500)
            return $artists[0]['picture_xl'] ?? $artists[0]['picture_big'] ?? null;
        } catch (\Throwable $e) {
            report($e);
            return null;
        }
    }
}

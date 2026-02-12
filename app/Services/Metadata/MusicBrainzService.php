<?php

declare(strict_types=1);

namespace App\Services\Metadata;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;

class MusicBrainzService
{
    private const BASE_URL = 'https://musicbrainz.org/ws/2';
    private const USER_AGENT = 'Muzakily/1.0 (https://github.com/muzakily)';
    private const RATE_LIMIT_KEY = 'musicbrainz_last_request';

    /**
     * Search for a recording by title, artist, and album.
     *
     * @return array{musicbrainz_id: string|null, artist_mbid: string|null, album_mbid: string|null, album_cover: string|null, artist_bio: string|null}|null
     */
    public function search(string $title, ?string $artist = null, ?string $album = null): ?array
    {
        $this->rateLimit();

        $query = sprintf('recording:"%s"', $this->escape($title));

        if ($artist) {
            $query .= sprintf(' AND artist:"%s"', $this->escape($artist));
        }

        if ($album) {
            $query .= sprintf(' AND release:"%s"', $this->escape($album));
        }

        try {
            $response = Http::withUserAgent(self::USER_AGENT)
                ->accept('application/json')
                ->withOptions([
                    'curl' => [
                        CURLOPT_IPRESOLVE => CURL_IPRESOLVE_V4,
                        CURLOPT_FRESH_CONNECT => true,
                    ],
                ])
                ->timeout(15)
                ->get(self::BASE_URL . '/recording', [
                    'query' => $query,
                    'limit' => 1,
                    'fmt' => 'json',
                ]);

            if (!$response->successful()) {
                return null;
            }

            $data = $response->json();
            $recordings = $data['recordings'] ?? [];

            if (empty($recordings)) {
                return null;
            }

            $recording = $recordings[0];

            $result = [
                'musicbrainz_id' => $recording['id'] ?? null,
                'artist_mbid' => null,
                'album_mbid' => null,
                'album_cover' => null,
                'artist_bio' => null,
            ];

            // Get artist MBID
            if (!empty($recording['artist-credit'])) {
                $result['artist_mbid'] = $recording['artist-credit'][0]['artist']['id'] ?? null;
            }

            // Get release (album) MBID
            if (!empty($recording['releases'])) {
                $release = $recording['releases'][0];
                $result['album_mbid'] = $release['id'] ?? null;

                // Try to get cover art
                if ($result['album_mbid']) {
                    $result['album_cover'] = $this->getCoverArt($result['album_mbid']);
                }
            }

            return $result;
        } catch (\Throwable $e) {
            report($e);
            return null;
        }
    }

    /**
     * Get cover art for an album from Cover Art Archive.
     * Note: Cover Art Archive has separate (more generous) rate limits than MusicBrainz.
     */
    private function getCoverArt(string $releaseId): ?string
    {
        try {
            $response = Http::withUserAgent(self::USER_AGENT)
                ->withOptions([
                    'curl' => [
                        CURLOPT_IPRESOLVE => CURL_IPRESOLVE_V4,
                        CURLOPT_FRESH_CONNECT => true,
                    ],
                ])
                ->timeout(15)
                ->get("https://coverartarchive.org/release/{$releaseId}/front");

            if ($response->successful()) {
                return "https://coverartarchive.org/release/{$releaseId}/front-250";
            }
        } catch (\Throwable $e) {
            // Cover art not available
        }

        return null;
    }

    /**
     * Escape special characters for Lucene query.
     */
    private function escape(string $value): string
    {
        // Escape backslash first to avoid double-escaping
        $value = str_replace('\\', '\\\\', $value);

        // Then escape other special Lucene characters
        $specialChars = ['+', '-', '&&', '||', '!', '(', ')', '{', '}', '[', ']', '^', '"', '~', '*', '?', ':', '/'];

        foreach ($specialChars as $char) {
            $value = str_replace($char, '\\' . $char, $value);
        }

        return $value;
    }

    /**
     * Apply rate limiting (MusicBrainz allows 1 request per second).
     */
    private function rateLimit(): void
    {
        $minInterval = 1000000 / config('muzakily.metadata.musicbrainz.rate_limit', 1); // microseconds
        $lastRequest = (float) Cache::get(self::RATE_LIMIT_KEY, 0);
        $now = microtime(true);
        $elapsed = ($now - $lastRequest) * 1000000; // convert to microseconds

        if ($elapsed < $minInterval) {
            usleep((int) ($minInterval - $elapsed));
        }

        Cache::put(self::RATE_LIMIT_KEY, microtime(true), 60);
    }
}

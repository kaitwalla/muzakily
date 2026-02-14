<?php

declare(strict_types=1);

namespace App\Services\Metadata;

use Illuminate\Support\Facades\Http;

class UnsplashService
{
    private const BASE_URL = 'https://api.unsplash.com';
    private const USER_AGENT = 'Muzakily/1.0';

    /**
     * Get a random photo from configured Unsplash collections.
     *
     * @return array{id: string, urls: array<string, string>, links: array<string, string>, user?: array<string, mixed>}|null
     */
    public function getRandomPhoto(): ?array
    {
        if (!$this->isEnabled()) {
            return null;
        }

        $accessKey = $this->getAccessKey();
        if ($accessKey === null) {
            return null;
        }

        try {
            $params = [];

            /** @var list<string> $collections */
            $collections = config('muzakily.metadata.unsplash.collections', []);
            if (!empty($collections)) {
                $params['collections'] = implode(',', $collections);
            }

            /** @var int $timeout */
            $timeout = config('muzakily.metadata.unsplash.timeout', 10);

            $response = Http::withUserAgent(self::USER_AGENT)
                ->withHeaders([
                    'Authorization' => 'Client-ID ' . $accessKey,
                ])
                ->timeout($timeout)
                ->get(self::BASE_URL . '/photos/random', $params);

            if (!$response->successful()) {
                return null;
            }

            /** @var array{id: string, urls: array<string, string>, links: array<string, string>, user?: array<string, mixed>} $data */
            $data = $response->json();

            return $data;
        } catch (\Throwable $e) {
            report($e);
            return null;
        }
    }

    /**
     * Track a photo download as required by Unsplash API guidelines.
     *
     * The Unsplash API requires that you trigger a download event when a user
     * downloads a photo for use in your application.
     *
     * @see https://help.unsplash.com/en/articles/2511258-guideline-triggering-a-download
     */
    public function trackDownload(string $downloadLocation): void
    {
        // Validate URL belongs to Unsplash to prevent SSRF
        $parsedUrl = parse_url($downloadLocation);
        $host = $parsedUrl['host'] ?? '';
        if (!str_ends_with($host, '.unsplash.com') && $host !== 'unsplash.com') {
            return;
        }

        $accessKey = $this->getAccessKey();
        if ($accessKey === null) {
            return;
        }

        try {
            /** @var int $timeout */
            $timeout = config('muzakily.metadata.unsplash.timeout', 10);

            Http::withUserAgent(self::USER_AGENT)
                ->withHeaders([
                    'Authorization' => 'Client-ID ' . $accessKey,
                ])
                ->timeout($timeout)
                ->get($downloadLocation);
        } catch (\Throwable $e) {
            // Silently fail - tracking is best-effort
            report($e);
        }
    }

    /**
     * Check if Unsplash integration is enabled.
     */
    private function isEnabled(): bool
    {
        return (bool) config('muzakily.metadata.unsplash.enabled', false);
    }

    /**
     * Get the Unsplash access key.
     */
    private function getAccessKey(): ?string
    {
        /** @var string|null $key */
        $key = config('muzakily.metadata.unsplash.access_key');

        return $key ?: null;
    }
}

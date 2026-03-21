<?php

declare(strict_types=1);

namespace App\Services\Metadata;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Process;

class AcoustIdService
{
    private const API_URL = 'https://api.acoustid.org/v2/lookup';

    public function __construct(
        private readonly string $apiKey,
        private readonly float $minScore = 0.5,
        private readonly string $fpcalcBin = 'fpcalc',
    ) {}

    /**
     * Fingerprint a local audio file and look it up on AcoustID.
     *
     * Returns the best-matching MusicBrainz recording ID, or null if none found
     * above the minimum confidence threshold.
     */
    public function lookup(string $localFilePath): ?string
    {
        $fingerprint = $this->fingerprint($localFilePath);

        if ($fingerprint === null) {
            return null;
        }

        return $this->queryApi($fingerprint['fingerprint'], $fingerprint['duration']);
    }

    /**
     * Run fpcalc on the file and return fingerprint + duration.
     *
     * @return array{fingerprint: string, duration: int}|null
     */
    private function fingerprint(string $filePath): ?array
    {
        $result = Process::run($this->fpcalcBin . ' -json ' . escapeshellarg($filePath));

        if (!$result->successful()) {
            Log::warning('AcoustID: fpcalc failed', [
                'file' => $filePath,
                'error' => $result->errorOutput(),
            ]);
            return null;
        }

        /** @var array{fingerprint?: string, duration?: float} $data */
        $data = json_decode($result->output(), true) ?? [];

        if (empty($data['fingerprint']) || empty($data['duration'])) {
            return null;
        }

        return [
            'fingerprint' => $data['fingerprint'],
            'duration' => (int) round($data['duration']),
        ];
    }

    /**
     * Submit fingerprint to AcoustID API and return best MusicBrainz recording ID.
     */
    private function queryApi(string $fingerprint, int $duration): ?string
    {
        try {
            $response = Http::timeout(15)->post(self::API_URL, [
                'client' => $this->apiKey,
                'fingerprint' => $fingerprint,
                'duration' => $duration,
                'meta' => 'recordings',
                'format' => 'json',
            ]);

            if ($response->status() === 429) {
                throw new \RuntimeException('AcoustID rate limit exceeded');
            }

            if (!$response->successful()) {
                return null;
            }

            /** @var array{status?: string, results?: list<array{score?: float, recordings?: list<array{id: string}>}>} $data */
            $data = $response->json();

            if (($data['status'] ?? '') !== 'ok' || empty($data['results'])) {
                return null;
            }

            foreach ($data['results'] as $result) {
                $score = (float) ($result['score'] ?? 0);

                if ($score < $this->minScore) {
                    continue;
                }

                $recordingId = $result['recordings'][0]['id'] ?? null;

                if ($recordingId !== null) {
                    return $recordingId;
                }
            }
        } catch (\Throwable $e) {
            Log::warning('AcoustID: API request failed', ['error' => $e->getMessage()]);
        }

        return null;
    }
}

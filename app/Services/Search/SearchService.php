<?php

declare(strict_types=1);

namespace App\Services\Search;

use Illuminate\Support\Facades\Log;
use Meilisearch\Exceptions\CommunicationException;

class SearchService
{
    public function __construct(
        private MeilisearchService $meilisearch,
        private PostgresSearchService $postgres,
    ) {}

    /**
     * Perform a search, falling back to PostgreSQL if Meilisearch is unavailable.
     *
     * @param array<string, mixed> $options
     * @return array<string, mixed>
     */
    public function search(string $query, array $options = []): array
    {
        // Check if Meilisearch is available and configured
        if ($this->shouldUseMeilisearch()) {
            try {
                return $this->meilisearch->search($query, $options);
            } catch (CommunicationException $e) {
                Log::warning('Meilisearch unavailable, falling back to PostgreSQL', [
                    'error' => $e->getMessage(),
                ]);
            } catch (\Throwable $e) {
                Log::error('Meilisearch error, falling back to PostgreSQL', [
                    'error' => $e->getMessage(),
                ]);
            }
        }

        // Fallback to PostgreSQL
        return $this->postgres->search($query, $options);
    }

    /**
     * Check if we should use Meilisearch.
     */
    private function shouldUseMeilisearch(): bool
    {
        // Check if Scout driver is set to meilisearch
        $driver = config('scout.driver');

        if ($driver !== 'meilisearch') {
            return false;
        }

        // Check if Meilisearch is available
        return $this->meilisearch->isAvailable();
    }

    /**
     * Get the current search engine being used.
     */
    public function getEngine(): string
    {
        return $this->shouldUseMeilisearch() ? 'meilisearch' : 'postgresql';
    }

    /**
     * Check if Meilisearch is available.
     */
    public function isMeilisearchAvailable(): bool
    {
        return $this->meilisearch->isAvailable();
    }

    /**
     * Get Meilisearch statistics.
     *
     * @return array<string, mixed>
     */
    public function getMeilisearchStats(): array
    {
        return $this->meilisearch->getStats();
    }

    /**
     * Reindex all models in Meilisearch.
     */
    public function reindexAll(): void
    {
        $this->meilisearch->reindexAll();
    }
}

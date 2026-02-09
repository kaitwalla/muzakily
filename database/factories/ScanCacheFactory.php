<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\ScanCache;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\ScanCache>
 */
class ScanCacheFactory extends Factory
{
    protected $model = ScanCache::class;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $objectKey = sprintf(
            '%s/%s/%s.%s',
            fake()->word(),
            fake()->word(),
            fake()->uuid(),
            fake()->randomElement(['mp3', 'aac', 'flac'])
        );

        return [
            'bucket' => 'muzakily',
            'object_key' => $objectKey,
            'key_hash' => hash('sha256', $objectKey),
            'etag' => fake()->sha256(),
            'size' => fake()->numberBetween(1_000_000, 50_000_000),
            'last_modified' => fake()->dateTimeBetween('-1 year', 'now'),
            'last_scanned_at' => fake()->dateTimeBetween('-1 month', 'now'),
        ];
    }

    /**
     * Mark as recently scanned.
     */
    public function recentlyScanned(): static
    {
        return $this->state(fn (array $attributes) => [
            'last_scanned_at' => now(),
        ]);
    }

    /**
     * Mark as stale (needs re-scanning).
     */
    public function stale(): static
    {
        return $this->state(fn (array $attributes) => [
            'last_scanned_at' => fake()->dateTimeBetween('-1 year', '-6 months'),
        ]);
    }
}

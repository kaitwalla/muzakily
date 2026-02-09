<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

/**
 * @property int $id
 * @property string $bucket
 * @property string $object_key
 * @property string $key_hash
 * @property string|null $etag
 * @property int $size
 * @property \Illuminate\Support\Carbon|null $last_modified
 * @property \Illuminate\Support\Carbon|null $last_scanned_at
 */
class ScanCache extends Model
{
    /** @use HasFactory<\Database\Factories\ScanCacheFactory> */
    use HasFactory;

    /**
     * The table associated with the model.
     *
     * @var string
     */
    protected $table = 'scan_cache';

    /**
     * Indicates if the model should be timestamped.
     *
     * @var bool
     */
    public $timestamps = false;

    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $fillable = [
        'bucket',
        'object_key',
        'key_hash',
        'etag',
        'size',
        'last_modified',
        'last_scanned_at',
    ];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'size' => 'integer',
            'last_modified' => 'datetime',
            'last_scanned_at' => 'datetime',
        ];
    }

    /**
     * Generate a hash for an object key.
     */
    public static function hashKey(string $key): string
    {
        return hash('sha256', $key);
    }

    /**
     * Find or create a cache entry.
     */
    public static function findOrCreateForKey(string $bucket, string $objectKey): self
    {
        $keyHash = self::hashKey($objectKey);

        return self::firstOrCreate(
            ['bucket' => $bucket, 'key_hash' => $keyHash],
            ['object_key' => $objectKey]
        );
    }

    /**
     * Check if the object has changed since last scan.
     */
    public function hasChanged(?string $etag, ?int $size = null): bool
    {
        if ($this->etag === null) {
            return true;
        }

        return $this->etag !== $etag || ($size !== null && $this->size !== $size);
    }

    /**
     * Update the cache entry with new metadata.
     */
    public function updateFromScan(string $etag, int $size, ?\DateTimeInterface $lastModified = null): void
    {
        $this->update([
            'etag' => $etag,
            'size' => $size,
            'last_modified' => $lastModified,
            'last_scanned_at' => now(),
        ]);
    }

    /**
     * Mark the entry as scanned.
     */
    public function markScanned(): void
    {
        $this->update(['last_scanned_at' => now()]);
    }

    /**
     * Get entries that haven't been scanned recently.
     *
     * @return \Illuminate\Database\Eloquent\Builder<ScanCache>
     */
    public static function stale(string $bucket, \DateTimeInterface $threshold): \Illuminate\Database\Eloquent\Builder
    {
        return self::where('bucket', $bucket)
            ->where(function ($query) use ($threshold) {
                $query->whereNull('last_scanned_at')
                    ->orWhere('last_scanned_at', '<', $threshold);
            });
    }
}

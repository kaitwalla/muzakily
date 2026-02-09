<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * @property int $id
 * @property string $name
 * @property string $path_prefix
 * @property int|null $parent_id
 * @property int $depth
 * @property bool $is_special
 * @property int $song_count
 * @property \Illuminate\Support\Carbon $created_at
 * @property \Illuminate\Support\Carbon $updated_at
 * @property-read SmartFolder|null $parent
 * @property-read \Illuminate\Database\Eloquent\Collection<int, SmartFolder> $children
 * @property-read \Illuminate\Database\Eloquent\Collection<int, Song> $songs
 */
class SmartFolder extends Model
{
    /** @use HasFactory<\Database\Factories\SmartFolderFactory> */
    use HasFactory;

    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $fillable = [
        'name',
        'path_prefix',
        'parent_id',
        'depth',
        'is_special',
        'song_count',
    ];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'depth' => 'integer',
            'is_special' => 'boolean',
            'song_count' => 'integer',
        ];
    }

    /**
     * Get the parent folder.
     *
     * @return BelongsTo<SmartFolder, $this>
     */
    public function parent(): BelongsTo
    {
        return $this->belongsTo(SmartFolder::class, 'parent_id');
    }

    /**
     * Get the child folders.
     *
     * @return HasMany<SmartFolder, $this>
     */
    public function children(): HasMany
    {
        return $this->hasMany(SmartFolder::class, 'parent_id');
    }

    /**
     * Get the songs in this folder.
     *
     * @return HasMany<Song, $this>
     */
    public function songs(): HasMany
    {
        return $this->hasMany(Song::class);
    }

    /**
     * Extract the smart folder path from a storage path.
     *
     * @param array<string> $specialFolders
     */
    public static function extractFromPath(string $storagePath, array $specialFolders = []): ?string
    {
        // Remove protocol prefix if present (s3://, r2://, etc.)
        $path = preg_replace('#^[a-z0-9]+://#i', '', $storagePath);
        if ($path === null) {
            return null;
        }

        // Split path into segments
        $segments = array_filter(explode('/', $path));
        $segments = array_values($segments); // Re-index

        // Need at least bucket + folder + file
        if (count($segments) < 3) {
            return null;
        }

        // Skip bucket name (first segment)
        array_shift($segments);

        // Get first folder
        $firstFolder = $segments[0] ?? null;
        if ($firstFolder === null) {
            return null;
        }

        // Check if this is a special folder that uses second level
        if (in_array($firstFolder, $specialFolders, true) && isset($segments[1])) {
            // Check if second segment is a folder (not a file)
            // Use a more robust heuristic: check for common audio extensions
            $secondSegment = $segments[1];
            $audioExtensions = ['mp3', 'aac', 'm4a', 'flac', 'wav', 'ogg', 'wma'];
            $extension = strtolower(pathinfo($secondSegment, PATHINFO_EXTENSION));
            $isFile = in_array($extension, $audioExtensions, true);
            if (!$isFile) {
                return $firstFolder . '/' . $secondSegment;
            }
        }

        return $firstFolder;
    }

    /**
     * Find or create a smart folder from a path prefix.
     *
     * @param array<string> $specialFolders
     */
    public static function findOrCreateFromPath(string $pathPrefix, array $specialFolders = []): self
    {
        // Determine depth and parent first (for nested folders)
        $segments = explode('/', $pathPrefix);
        $depth = count($segments);
        $name = end($segments);
        $parentId = null;
        $isSpecial = false;

        if ($depth > 1) {
            $parentPrefix = implode('/', array_slice($segments, 0, -1));
            $parent = self::findOrCreateFromPath($parentPrefix, $specialFolders);
            $parentId = $parent->id;
            $isSpecial = $parent->is_special || in_array($segments[0], $specialFolders, true);
        } else {
            $isSpecial = in_array($name, $specialFolders, true);
        }

        // Use firstOrCreate to handle race conditions
        return self::firstOrCreate(
            ['path_prefix' => $pathPrefix],
            [
                'name' => $name,
                'parent_id' => $parentId,
                'depth' => $depth,
                'is_special' => $isSpecial,
            ]
        );
    }

    /**
     * Update the song count for this folder.
     */
    public function updateSongCount(): void
    {
        $this->update(['song_count' => $this->songs()->count()]);
    }

    /**
     * Get all available folders for filtering.
     *
     * @return \Illuminate\Database\Eloquent\Collection<int, SmartFolder>
     */
    public static function getAvailableFolders(): \Illuminate\Database\Eloquent\Collection
    {
        return self::whereNull('parent_id')
            ->with('children')
            ->orderBy('name')
            ->get();
    }
}

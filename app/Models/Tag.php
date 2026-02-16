<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Str;

/**
 * @property int $id
 * @property string $name
 * @property string $slug
 * @property string|null $color
 * @property int|null $parent_id
 * @property int $depth
 * @property bool $is_special
 * @property int $song_count
 * @property string|null $auto_assign_pattern
 * @property \Illuminate\Support\Carbon|null $created_at
 * @property \Illuminate\Support\Carbon|null $updated_at
 * @property-read Tag|null $parent
 * @property-read \Illuminate\Database\Eloquent\Collection<int, Tag> $children
 * @property-read \Illuminate\Database\Eloquent\Collection<int, Song> $songs
 */
class Tag extends Model
{
    /** @use HasFactory<\Database\Factories\TagFactory> */
    use HasFactory;

    /**
     * @var list<string>
     */
    protected $fillable = [
        'name',
        'slug',
        'color',
        'parent_id',
        'depth',
        'is_special',
        'song_count',
        'auto_assign_pattern',
    ];

    /**
     * @var array<string, string>
     */
    protected $casts = [
        'is_special' => 'boolean',
        'song_count' => 'integer',
        'depth' => 'integer',
    ];

    protected static function booted(): void
    {
        static::creating(function (Tag $tag) {
            if (empty($tag->slug)) {
                $tag->slug = static::generateUniqueSlug($tag->name);
            }
        });
    }

    /**
     * @return BelongsTo<Tag, $this>
     */
    public function parent(): BelongsTo
    {
        return $this->belongsTo(Tag::class, 'parent_id');
    }

    /**
     * @return HasMany<Tag, $this>
     */
    public function children(): HasMany
    {
        return $this->hasMany(Tag::class, 'parent_id');
    }

    /**
     * @return BelongsToMany<Song, $this>
     */
    public function songs(): BelongsToMany
    {
        return $this->belongsToMany(Song::class, 'song_tag')
            ->withPivot('auto_assigned', 'created_at');
    }

    /**
     * Scope to get only root-level tags (no parent).
     *
     * @param Builder<Tag> $query
     * @return Builder<Tag>
     */
    public function scopeRoots(Builder $query): Builder
    {
        return $query->whereNull('parent_id');
    }

    /**
     * Scope to include song counts.
     *
     * @param Builder<Tag> $query
     * @return Builder<Tag>
     */
    public function scopeWithSongCount(Builder $query): Builder
    {
        return $query->withCount('songs');
    }

    /**
     * Extract tag names from a storage path.
     * For special folders (like xmas), returns multiple tags.
     *
     * @param list<string> $specialFolders
     * @return list<string>
     */
    public static function extractTagNamesFromPath(string $path, array $specialFolders = []): array
    {
        if (empty($path)) {
            return [];
        }

        $parts = explode('/', $path);

        // Need at least 2 parts (folder/file)
        if (count($parts) < 2) {
            return [];
        }

        $topLevel = $parts[0];

        // Check if it's a special folder (case-insensitive) and has a subfolder
        $isSpecial = false;
        foreach ($specialFolders as $specialFolder) {
            if (strcasecmp($topLevel, $specialFolder) === 0) {
                $isSpecial = true;
                break;
            }
        }

        if ($isSpecial && count($parts) >= 3) {
            // Return both the parent tag and the combined "Parent - Child" tag
            return [
                strtolower($topLevel),
                strtolower($topLevel) . ' - ' . strtolower($parts[1]),
            ];
        }

        // For special folders without subfolder, lowercase
        if ($isSpecial) {
            return [strtolower($topLevel)];
        }

        return [$topLevel];
    }

    /**
     * Find or create tags from a storage path.
     * For special folders (like xmas), creates multiple flat tags.
     *
     * @param list<string> $specialFolders
     * @return \Illuminate\Database\Eloquent\Collection<int, self>
     */
    public static function findOrCreateTagsFromPath(string $path, array $specialFolders = []): \Illuminate\Database\Eloquent\Collection
    {
        $tagNames = static::extractTagNamesFromPath($path, $specialFolders);

        if (empty($tagNames)) {
            return new \Illuminate\Database\Eloquent\Collection();
        }

        $tags = [];

        foreach ($tagNames as $tagName) {
            // Check by name first (case-insensitive for consistency)
            $tag = static::whereRaw('LOWER(name) = ?', [strtolower($tagName)])->first();
            if (!$tag) {
                $tag = static::create([
                    'name' => $tagName,
                    'slug' => static::generateUniqueSlug($tagName),
                    'depth' => 1,
                ]);
            }
            $tags[] = $tag;
        }

        return new \Illuminate\Database\Eloquent\Collection($tags);
    }

    /**
     * Update the song count for this tag.
     */
    public function updateSongCount(): void
    {
        $this->update(['song_count' => $this->songs()->count()]);
    }

    /**
     * Generate a unique slug from a name.
     * Uses optimized query to find the highest existing counter.
     */
    public static function generateUniqueSlug(string $name): string
    {
        $slug = Str::slug($name);
        $originalSlug = $slug;

        // Check if base slug exists or any suffixed versions
        $latestSlug = static::where('slug', $slug)
            ->orWhere('slug', 'like', $originalSlug . '-%')
            ->orderByRaw('LENGTH(slug) DESC, slug DESC')
            ->value('slug');

        if ($latestSlug === null) {
            return $slug;
        }

        // If the exact base slug exists, or we have suffixed versions
        if ($latestSlug === $originalSlug) {
            return $originalSlug . '-1';
        }

        // Extract counter from latest suffixed slug
        if (preg_match('/-(\d+)$/', $latestSlug, $matches)) {
            $counter = (int) $matches[1] + 1;
            return $originalSlug . '-' . $counter;
        }

        return $originalSlug . '-1';
    }

    /**
     * Get all descendant tag IDs (for inclusive filtering).
     *
     * @return list<int>
     */
    public function getDescendantIds(): array
    {
        $ids = [];

        foreach ($this->children as $child) {
            $ids[] = $child->id;
            $ids = array_merge($ids, $child->getDescendantIds());
        }

        return $ids;
    }

    /**
     * Update the depth of this tag and all its descendants.
     */
    public function updateDescendantDepths(): void
    {
        $this->children->each(function (Tag $child): void {
            $child->update(['depth' => $this->depth + 1]);
            $child->updateDescendantDepths();
        });
    }

    /**
     * Get default color for a tag based on its name.
     */
    public static function getDefaultColor(string $name): string
    {
        /** @var array<string, string> $defaultColors */
        $defaultColors = config('muzakily.tags.default_colors', []);

        // Check exact match first
        if (isset($defaultColors[$name])) {
            return $defaultColors[$name];
        }

        // Check parent name for hierarchical tags
        if (str_contains($name, '/')) {
            $parentName = explode('/', $name)[0];
            if (isset($defaultColors[$parentName])) {
                return $defaultColors[$parentName];
            }
        }

        // Generate a color based on the name hash
        // Use abs() to handle negative values on 32-bit systems
        $hash = abs(crc32($name));
        $hue = $hash % 360;

        return sprintf('#%02x%02x%02x', ...self::hslToRgb($hue, 0.65, 0.5));
    }

    /**
     * Convert HSL to RGB.
     *
     * @return array{0: int, 1: int, 2: int}
     */
    private static function hslToRgb(float $h, float $s, float $l): array
    {
        $h = $h / 360;

        if ($s === 0.0) {
            $r = $g = $b = $l;
        } else {
            $q = $l < 0.5 ? $l * (1 + $s) : $l + $s - $l * $s;
            $p = 2 * $l - $q;
            $r = self::hueToRgb($p, $q, $h + 1 / 3);
            $g = self::hueToRgb($p, $q, $h);
            $b = self::hueToRgb($p, $q, $h - 1 / 3);
        }

        return [(int) round($r * 255), (int) round($g * 255), (int) round($b * 255)];
    }

    private static function hueToRgb(float $p, float $q, float $t): float
    {
        if ($t < 0) {
            $t += 1;
        }
        if ($t > 1) {
            $t -= 1;
        }
        if ($t < 1 / 6) {
            return $p + ($q - $p) * 6 * $t;
        }
        if ($t < 1 / 2) {
            return $q;
        }
        if ($t < 2 / 3) {
            return $p + ($q - $p) * (2 / 3 - $t) * 6;
        }

        return $p;
    }
}

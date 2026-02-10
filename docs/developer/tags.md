# Tags System

Hierarchical tagging system for flexible song organization with auto-assignment capabilities.

## Data Model

### Tag Model

```php
class Tag extends Model
{
    protected $fillable = [
        'name',
        'slug',
        'color',
        'parent_id',
        'auto_assign_pattern',
    ];

    public function parent(): BelongsTo
    {
        return $this->belongsTo(Tag::class, 'parent_id');
    }

    public function children(): HasMany
    {
        return $this->hasMany(Tag::class, 'parent_id');
    }

    public function songs(): BelongsToMany
    {
        return $this->belongsToMany(Song::class, 'song_tag');
    }

    public function getSongCountAttribute(): int
    {
        return $this->songs()->count();
    }

    public function getDepthAttribute(): int
    {
        $depth = 0;
        $parent = $this->parent;

        while ($parent) {
            $depth++;
            $parent = $parent->parent;
        }

        return $depth;
    }

    public function getAllDescendants(): Collection
    {
        $descendants = collect();

        foreach ($this->children as $child) {
            $descendants->push($child);
            $descendants = $descendants->merge($child->getAllDescendants());
        }

        return $descendants;
    }
}
```

### Database Schema

```php
Schema::create('tags', function (Blueprint $table) {
    $table->id();
    $table->string('name');
    $table->string('slug')->unique();
    $table->string('color', 7)->default('#6366f1');
    $table->foreignId('parent_id')->nullable()->constrained('tags')->nullOnDelete();
    $table->string('auto_assign_pattern')->nullable();
    $table->timestamps();

    $table->index('parent_id');
    $table->index('slug');
});

Schema::create('song_tag', function (Blueprint $table) {
    $table->foreignUuid('song_id')->constrained()->cascadeOnDelete();
    $table->foreignId('tag_id')->constrained()->cascadeOnDelete();
    $table->timestamps();

    $table->primary(['song_id', 'tag_id']);
});
```

## Tag Service

### TagService

```php
namespace App\Services;

class TagService
{
    /**
     * Create a new tag.
     */
    public function create(array $data): Tag
    {
        return Tag::create([
            'name' => $data['name'],
            'slug' => Str::slug($data['name']),
            'color' => $data['color'] ?? '#6366f1',
            'parent_id' => $data['parent_id'] ?? null,
            'auto_assign_pattern' => $data['auto_assign_pattern'] ?? null,
        ]);
    }

    /**
     * Update tag, preventing circular hierarchies.
     */
    public function update(Tag $tag, array $data): Tag
    {
        if (isset($data['parent_id'])) {
            $this->validateParent($tag, $data['parent_id']);
        }

        $tag->update($data);

        return $tag->fresh();
    }

    /**
     * Validate parent doesn't create circular reference.
     */
    protected function validateParent(Tag $tag, ?int $parentId): void
    {
        if ($parentId === null) {
            return;
        }

        if ($parentId === $tag->id) {
            throw new InvalidParentException('A tag cannot be its own parent.');
        }

        $descendants = $tag->getAllDescendants()->pluck('id');

        if ($descendants->contains($parentId)) {
            throw new InvalidParentException('Cannot set a descendant as parent.');
        }
    }

    /**
     * Add tags to a song.
     */
    public function tagSong(Song $song, array $tagIds): void
    {
        $song->tags()->syncWithoutDetaching($tagIds);
    }

    /**
     * Remove tags from a song.
     */
    public function untagSong(Song $song, array $tagIds): void
    {
        $song->tags()->detach($tagIds);
    }
}
```

## Auto-Assignment

### AutoTagService

```php
namespace App\Services;

class AutoTagService
{
    /**
     * Apply auto-tags to a song based on its metadata.
     */
    public function applyAutoTags(Song $song): void
    {
        $tags = Tag::whereNotNull('auto_assign_pattern')->get();

        foreach ($tags as $tag) {
            if ($this->matchesPattern($song, $tag->auto_assign_pattern)) {
                $song->tags()->syncWithoutDetaching([$tag->id]);
            }
        }
    }

    /**
     * Check if song matches the pattern.
     */
    protected function matchesPattern(Song $song, string $pattern): bool
    {
        // Check against title
        if (@preg_match("/{$pattern}/i", $song->title)) {
            return true;
        }

        // Check against artist name
        if (@preg_match("/{$pattern}/i", $song->artist_name)) {
            return true;
        }

        // Check against album name
        if (@preg_match("/{$pattern}/i", $song->album_name)) {
            return true;
        }

        return false;
    }

    /**
     * Re-apply auto-tags to all songs for a specific tag.
     */
    public function reapplyForTag(Tag $tag): int
    {
        if (!$tag->auto_assign_pattern) {
            return 0;
        }

        $count = 0;

        Song::cursor()->each(function ($song) use ($tag, &$count) {
            if ($this->matchesPattern($song, $tag->auto_assign_pattern)) {
                $song->tags()->syncWithoutDetaching([$tag->id]);
                $count++;
            }
        });

        return $count;
    }
}
```

### Integration with Scanner

```php
class LibraryScannerService
{
    public function __construct(
        protected AutoTagService $autoTagService
    ) {}

    protected function processFile(string $path): Song
    {
        $song = $this->createSong($path);

        // Apply auto-tags
        $this->autoTagService->applyAutoTags($song);

        return $song;
    }
}
```

## API Endpoints

### TagController

```php
class TagController extends Controller
{
    public function index(Request $request): AnonymousResourceCollection
    {
        $flat = $request->boolean('flat', false);

        if ($flat) {
            return TagResource::collection(Tag::all());
        }

        // Hierarchical
        $tags = Tag::whereNull('parent_id')
            ->with('children.children')
            ->get();

        return TagResource::collection($tags);
    }

    public function store(CreateTagRequest $request, TagService $tagService): TagResource
    {
        $tag = $tagService->create($request->validated());

        return new TagResource($tag);
    }

    public function update(UpdateTagRequest $request, Tag $tag, TagService $tagService): TagResource
    {
        $tag = $tagService->update($tag, $request->validated());

        return new TagResource($tag);
    }

    public function destroy(Tag $tag): Response
    {
        $this->authorize('delete', $tag);

        $tag->delete();

        return response()->noContent();
    }

    public function songs(Request $request, Tag $tag): AnonymousResourceCollection
    {
        $includeChildren = $request->boolean('include_children', false);

        if ($includeChildren) {
            $tagIds = collect([$tag->id])
                ->merge($tag->getAllDescendants()->pluck('id'));

            $songs = Song::whereHas('tags', fn ($q) => $q->whereIn('id', $tagIds))
                ->paginate();
        } else {
            $songs = $tag->songs()->paginate();
        }

        return SongResource::collection($songs);
    }
}
```

### Song Tags

```php
class SongTagController extends Controller
{
    public function store(Request $request, Song $song, TagService $tagService)
    {
        $request->validate(['tag_ids' => 'required|array']);

        $tagService->tagSong($song, $request->input('tag_ids'));

        return response()->json([
            'data' => [
                'song_id' => $song->id,
                'tags' => TagResource::collection($song->tags),
            ],
        ]);
    }

    public function destroy(Request $request, Song $song, TagService $tagService)
    {
        $request->validate(['tag_ids' => 'required|array']);

        $tagService->untagSong($song, $request->input('tag_ids'));

        return response()->json([
            'data' => [
                'song_id' => $song->id,
                'tags' => TagResource::collection($song->tags),
            ],
        ]);
    }
}
```

## Smart Playlist Integration

### Tag Field Handler

```php
class TagFieldHandler extends FieldHandler
{
    public function apply(Builder $query, string $operator, mixed $value, string $method): void
    {
        match ($operator) {
            'has' => $this->applyHas($query, $value, $method),
            'has_any' => $this->applyHasAny($query, $value, $method),
        };
    }

    protected function applyHas(Builder $query, int $tagId, string $method): void
    {
        $query->{$method . 'Has'}('tags', fn ($q) => $q->where('tags.id', $tagId));
    }

    protected function applyHasAny(Builder $query, array $tagIds, string $method): void
    {
        $query->{$method . 'Has'}('tags', fn ($q) => $q->whereIn('tags.id', $tagIds));
    }
}
```

## Validation

### CreateTagRequest

```php
class CreateTagRequest extends FormRequest
{
    public function rules(): array
    {
        return [
            'name' => 'required|string|max:255',
            'color' => ['nullable', 'string', 'regex:/^#[0-9A-Fa-f]{6}$/'],
            'parent_id' => 'nullable|exists:tags,id',
            'auto_assign_pattern' => ['nullable', 'string', new ValidRegex()],
        ];
    }
}
```

### ValidRegex Rule

```php
class ValidRegex implements ValidationRule
{
    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        if (@preg_match("/{$value}/", '') === false) {
            $fail('The :attribute must be a valid regular expression.');
        }
    }
}
```

## Frontend Integration

### Tag Manager Component

```vue
<template>
  <div class="tag-manager">
    <TagTree :tags="rootTags" @select="selectTag" />
    <TagEditor v-if="selectedTag" :tag="selectedTag" @save="saveTag" />
  </div>
</template>

<script setup lang="ts">
const tagsStore = useTagsStore();

const rootTags = computed(() =>
  tagsStore.tags.filter(t => !t.parent_id)
);
</script>
```

### Tagging Songs

```typescript
async function addTagsToSong(songId: string, tagIds: number[]) {
  await api.post(`/songs/${songId}/tags`, { tag_ids: tagIds });
}

async function removeTagsFromSong(songId: string, tagIds: number[]) {
  await api.delete(`/songs/${songId}/tags`, { data: { tag_ids: tagIds } });
}
```

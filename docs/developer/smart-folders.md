# Smart Folders

Smart folders organize songs based on their file paths in R2 storage. This document covers the technical implementation.

## Data Model

### SmartFolder Model

```php
class SmartFolder extends Model
{
    protected $fillable = [
        'name',
        'path',
        'parent_id',
        'depth',
    ];

    public function parent(): BelongsTo
    {
        return $this->belongsTo(SmartFolder::class, 'parent_id');
    }

    public function children(): HasMany
    {
        return $this->hasMany(SmartFolder::class, 'parent_id');
    }

    public function songs(): HasMany
    {
        return $this->hasMany(Song::class);
    }

    /**
     * Get all songs in this folder and subfolders.
     * Note: For recursive queries, use getAllDescendantIds() with whereIn instead.
     */
    public function allSongs(): HasMany
    {
        return $this->songs();
    }

    public function getSongCountAttribute(): int
    {
        return Song::whereIn('smart_folder_id', $this->getAllDescendantIds())->count();
    }

    public function getAllDescendantIds(): array
    {
        $ids = [$this->id];
        foreach ($this->children as $child) {
            $ids = array_merge($ids, $child->getAllDescendantIds());
        }
        return $ids;
    }
}
```

### Database Schema

```php
Schema::create('smart_folders', function (Blueprint $table) {
    $table->id();
    $table->string('name');
    $table->string('path')->unique();
    $table->foreignId('parent_id')->nullable()->constrained('smart_folders')->nullOnDelete();
    $table->unsignedInteger('depth')->default(0);
    $table->timestamps();

    $table->index('parent_id');
    $table->index('path');
});

Schema::table('songs', function (Blueprint $table) {
    $table->foreignId('smart_folder_id')->nullable()->constrained()->nullOnDelete();
});
```

## Folder Assignment

### SmartFolderService

```php
namespace App\Services;

class SmartFolderService
{
    /**
     * Assign a song to its smart folder based on file path.
     */
    public function assignFromPath(Song $song, string $filePath): void
    {
        $folder = $this->getOrCreateFolderForPath($filePath);
        $song->update(['smart_folder_id' => $folder->id]);
    }

    /**
     * Get or create the folder hierarchy for a path.
     */
    public function getOrCreateFolderForPath(string $filePath): SmartFolder
    {
        $directory = dirname($filePath);
        $parts = array_filter(explode('/', $directory));

        $parent = null;
        $currentPath = '';

        foreach ($parts as $depth => $name) {
            $currentPath = $currentPath ? "{$currentPath}/{$name}" : $name;

            $folder = SmartFolder::firstOrCreate(
                ['path' => $currentPath],
                [
                    'name' => $name,
                    'parent_id' => $parent?->id,
                    'depth' => $depth,
                ]
            );

            $parent = $folder;
        }

        return $parent;
    }

    /**
     * Rebuild folder hierarchy from existing songs.
     */
    public function rebuildFromSongs(): void
    {
        SmartFolder::truncate();

        Song::select('file_path')->cursor()->each(function ($song) {
            $this->getOrCreateFolderForPath($song->file_path);
        });

        // Re-assign songs
        Song::cursor()->each(function ($song) {
            $this->assignFromPath($song, $song->file_path);
        });
    }

    /**
     * Clean up empty folders.
     */
    public function removeEmpty(): int
    {
        $removed = 0;

        SmartFolder::doesntHave('songs')
            ->doesntHave('children')
            ->get()
            ->each(function ($folder) use (&$removed) {
                $folder->delete();
                $removed++;
            });

        return $removed;
    }
}
```

## Tree Building

### Getting Folder Tree

```php
class SmartFolderController extends Controller
{
    public function index(): AnonymousResourceCollection
    {
        // Get top-level folders with nested children
        $folders = SmartFolder::whereNull('parent_id')
            ->with('children.children.children') // Load 3 levels
            ->get();

        return SmartFolderResource::collection($folders);
    }
}
```

### Recursive Resource

```php
class SmartFolderResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'path' => $this->path,
            'depth' => $this->depth,
            'song_count' => $this->song_count,
            'parent_id' => $this->parent_id,
            'children' => SmartFolderResource::collection(
                $this->whenLoaded('children')
            ),
        ];
    }
}
```

## Querying Songs

### By Folder (Including Subfolders)

```php
class SmartFolderController extends Controller
{
    public function songs(SmartFolder $smartFolder): AnonymousResourceCollection
    {
        // Get all descendant folder IDs
        $folderIds = $this->getDescendantIds($smartFolder);

        $songs = Song::whereIn('smart_folder_id', $folderIds)
            ->with(['artist', 'album', 'tags'])
            ->paginate();

        return SongResource::collection($songs);
    }

    protected function getDescendantIds(SmartFolder $folder): array
    {
        $ids = [$folder->id];

        foreach ($folder->children as $child) {
            $ids = array_merge($ids, $this->getDescendantIds($child));
        }

        return $ids;
    }
}
```

### Optimized with CTE

For deep hierarchies, use a recursive CTE:

```php
public function getAllDescendantIds(SmartFolder $folder): array
{
    $result = DB::select("
        WITH RECURSIVE folder_tree AS (
            SELECT id FROM smart_folders WHERE id = ?
            UNION ALL
            SELECT sf.id FROM smart_folders sf
            INNER JOIN folder_tree ft ON sf.parent_id = ft.id
        )
        SELECT id FROM folder_tree
    ", [$folder->id]);

    return array_column($result, 'id');
}
```

## Integration with Smart Playlists

### Smart Folder Filter

```php
class SmartFolderFieldHandler extends FieldHandler
{
    public function apply(Builder $query, string $operator, mixed $value, string $method): void
    {
        if ($operator === 'equals') {
            $folderIds = $this->getDescendantIds($value);
            $query->{$method . 'In'}('smart_folder_id', $folderIds);
        }
    }
}
```

### Example Rule

```json
{
  "rules": [
    {
      "logic": "and",
      "rules": [
        {
          "field": "smart_folder_id",
          "operator": "equals",
          "value": 5
        }
      ]
    }
  ]
}
```

## API Endpoints

### List Folders

```
GET /api/v1/smart-folders
```

Returns hierarchical tree of folders.

### Get Folder Songs

```
GET /api/v1/smart-folders/{id}/songs
```

Returns paginated songs in folder and subfolders.

### Filter Songs by Folder

```
GET /api/v1/songs?smart_folder_id=5
```

Returns songs in the specified folder (not including subfolders).

## Frontend Integration

### Folder Tree Component

```vue
<template>
  <div class="folder-tree">
    <FolderTreeItem
      v-for="folder in rootFolders"
      :key="folder.id"
      :folder="folder"
      :depth="0"
      @select="onFolderSelect"
    />
  </div>
</template>

<script setup lang="ts">
interface SmartFolder {
  id: number;
  name: string;
  path: string;
  song_count: number;
  children: SmartFolder[];
}

const props = defineProps<{
  folders: SmartFolder[];
}>();

const emit = defineEmits<{
  select: [folder: SmartFolder];
}>();

const rootFolders = computed(() =>
  props.folders.filter(f => !f.parent_id)
);
</script>
```

### Store

```typescript
export const useFoldersStore = defineStore('folders', () => {
  const folders = ref<SmartFolder[]>([]);

  async function fetchFolders() {
    const response = await api.get('/smart-folders');
    folders.value = response.data.data;
  }

  async function fetchFolderSongs(folderId: number) {
    const response = await api.get(`/smart-folders/${folderId}/songs`);
    return response.data.data;
  }

  return { folders, fetchFolders, fetchFolderSongs };
});
```

## Best Practices

### Path Normalization

```php
protected function normalizePath(string $path): string
{
    // Remove leading/trailing slashes
    $path = trim($path, '/');

    // Normalize separators
    $path = str_replace('\\', '/', $path);

    // Remove double slashes
    $path = preg_replace('#/+#', '/', $path);

    return $path;
}
```

### Handle Renames

When files are moved/renamed:

```php
public function handleFileMove(string $oldPath, string $newPath): void
{
    $song = Song::where('file_path', $oldPath)->first();

    if ($song) {
        $song->file_path = $newPath;
        $this->assignFromPath($song, $newPath);
        $song->save();
    }

    // Clean up old empty folders
    $this->removeEmpty();
}
```

### Caching

For large folder trees:

```php
public function getTree(): Collection
{
    return Cache::remember('smart_folder_tree', 300, function () {
        return SmartFolder::whereNull('parent_id')
            ->with('children.children.children')
            ->get();
    });
}

// Invalidate on changes using model events
SmartFolder::saved(function () {
    Cache::forget('smart_folder_tree');
});

SmartFolder::deleted(function () {
    Cache::forget('smart_folder_tree');
});
```

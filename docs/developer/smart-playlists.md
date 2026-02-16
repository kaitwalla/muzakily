# Smart Playlists

Smart playlists dynamically include songs based on rules. This document covers the technical implementation.

## Data Model

### Playlist Model

```php
class Playlist extends Model
{
    protected $casts = [
        'is_smart' => 'boolean',
        'rules' => 'array',
    ];

    public function songs(): BelongsToMany
    {
        return $this->belongsToMany(Song::class, 'playlist_song')
            ->withPivot('position')
            ->orderByPivot('position');
    }

    public function getSongsAttribute(): Collection
    {
        if ($this->is_smart) {
            return $this->evaluateRules();
        }

        return $this->songs()->get();
    }
}
```

### Rules Schema

```json
{
  "rules": [
    {
      "logic": "and",
      "rules": [
        {
          "field": "genre",
          "operator": "equals",
          "value": "Rock"
        },
        {
          "field": "year",
          "operator": "between",
          "value": [1980, 1989]
        }
      ]
    }
  ]
}
```

## Rule Evaluation

### SmartPlaylistEvaluator

The evaluator is located at `app/Services/Playlist/SmartPlaylistEvaluator.php`:

```php
namespace App\Services\Playlist;

class SmartPlaylistEvaluator
{
    /**
     * Evaluate a smart playlist and return matching songs.
     * Uses materialized results if available and up-to-date.
     */
    public function evaluate(Playlist $playlist, ?User $user = null): Collection;

    /**
     * Always evaluates rules from scratch (bypasses materialization).
     * Used by RefreshSmartPlaylistJob.
     */
    public function evaluateDynamic(Playlist $playlist, ?User $user = null): Collection;

    /**
     * Paginated evaluation for large playlists.
     * Returns ['songs' => Collection, 'total' => int]
     */
    public function evaluatePaginated(Playlist $playlist, ?User $user, int $limit, int $offset): array;

    /**
     * Check if a single song matches the playlist rules.
     * Used for incremental updates.
     */
    public function matches(Playlist $playlist, Song $song, ?User $user = null): bool;

    /**
     * Count matching songs without loading them.
     */
    public function count(Playlist $playlist, ?User $user = null): int;
}
```

The evaluator applies rule groups using AND/OR logic:

```php
private function applyRuleGroup(Builder $query, array $ruleGroup, ?User $user): void
{
    $logic = strtolower($ruleGroup['logic']);
    $rules = $ruleGroup['rules'];

    $query->where(function (Builder $groupQuery) use ($rules, $logic, $user) {
        foreach ($rules as $rule) {
            $method = $logic === 'or' ? 'orWhere' : 'where';
            $this->applyRule($groupQuery, $rule, $method, $user);
        }
    });
}

/**
 * Apply a single rule to the query builder.
 *
 * @param Builder $query The query builder to modify
 * @param array $rule The rule with field, operator, and value
 * @param string $method The where method to use ('where' or 'orWhere')
 * @param User|null $user The user context for user-specific rules (play_count, is_favorite, etc.)
 */
private function applyRule(Builder $query, array $rule, string $method, ?User $user): void;
```

### Field Handlers

```php
abstract class FieldHandler
{
    abstract public function apply(Builder $query, string $operator, mixed $value, string $method): void;
}

class StringFieldHandler extends FieldHandler
{
    public function __construct(
        protected string $column
    ) {}

    public function apply(Builder $query, string $operator, mixed $value, string $method): void
    {
        match ($operator) {
            'equals' => $query->{$method}($this->column, '=', $value),
            'not_equals' => $query->{$method}($this->column, '!=', $value),
            'contains' => $query->{$method}($this->column, 'ilike', "%{$value}%"),
            'starts_with' => $query->{$method}($this->column, 'ilike', "{$value}%"),
            'ends_with' => $query->{$method}($this->column, 'ilike', "%{$value}"),
        };
    }
}

class NumericFieldHandler extends FieldHandler
{
    public function __construct(
        protected string $column
    ) {}

    public function apply(Builder $query, string $operator, mixed $value, string $method): void
    {
        match ($operator) {
            'equals' => $query->{$method}($this->column, '=', $value),
            'greater_than' => $query->{$method}($this->column, '>', $value),
            'less_than' => $query->{$method}($this->column, '<', $value),
            'between' => $query->{$method . 'Between'}($this->column, $value),
        };
    }
}

class TagFieldHandler extends FieldHandler
{
    public function apply(Builder $query, string $operator, mixed $value, string $method): void
    {
        match ($operator) {
            'has' => $query->{$method . 'Has'}('tags', fn ($q) => $q->where('id', $value)),
            'has_any' => $query->{$method . 'Has'}('tags', fn ($q) => $q->whereIn('id', $value)),
        };
    }
}
```

### Field Registry

```php
class FieldRegistry
{
    protected array $handlers = [];

    public function __construct()
    {
        $this->register('title', new StringFieldHandler('title'));
        $this->register('artist_name', new StringFieldHandler('artist_name'));
        $this->register('album_name', new StringFieldHandler('album_name'));
        $this->register('genre', new StringFieldHandler('genre'));
        $this->register('year', new NumericFieldHandler('year'));
        $this->register('play_count', new NumericFieldHandler('play_count'));
        $this->register('length', new NumericFieldHandler('length'));
        $this->register('is_favorite', new BooleanFieldHandler('is_favorite'));
        $this->register('audio_format', new EnumFieldHandler('audio_format'));
        $this->register('smart_folder_id', new NumericFieldHandler('smart_folder_id'));
        $this->register('tag', new TagFieldHandler());
        $this->register('created_at', new DateFieldHandler('created_at'));
    }

    public function get(string $field): FieldHandler
    {
        return $this->handlers[$field] ?? throw new InvalidFieldException($field);
    }
}
```

## Validation

### CreatePlaylistRequest

```php
class CreatePlaylistRequest extends FormRequest
{
    public function rules(): array
    {
        return [
            'name' => 'required|string|max:255',
            'is_smart' => 'boolean',
            'rules' => 'required_if:is_smart,true|array',
            'rules.*.logic' => 'required|in:and,or',
            'rules.*.rules' => 'required|array|min:1',
            'rules.*.rules.*.field' => [
                'required',
                'string',
                Rule::in($this->validFields()),
            ],
            'rules.*.rules.*.operator' => 'required|string',
            'rules.*.rules.*.value' => 'required',
        ];
    }

    protected function validFields(): array
    {
        return [
            'title', 'artist_name', 'album_name', 'genre',
            'year', 'play_count', 'length', 'is_favorite',
            'audio_format', 'smart_folder_id', 'tag', 'created_at',
        ];
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function ($validator) {
            if ($this->input('is_smart')) {
                $this->validateOperators($validator);
            }
        });
    }

    protected function validateOperators(Validator $validator): void
    {
        // Validate operators match field types
        foreach ($this->input('rules', []) as $gi => $group) {
            foreach ($group['rules'] ?? [] as $ri => $rule) {
                $field = $rule['field'] ?? '';
                $operator = $rule['operator'] ?? '';

                if (!$this->isValidOperator($field, $operator)) {
                    $validator->errors()->add(
                        "rules.{$gi}.rules.{$ri}.operator",
                        "Invalid operator '{$operator}' for field '{$field}'"
                    );
                }
            }
        }
    }
}
```

## API Endpoints

### Get Playlist Songs

```php
class PlaylistController extends Controller
{
    public function songs(Playlist $playlist): AnonymousResourceCollection
    {
        $this->authorize('view', $playlist);

        if ($playlist->is_smart) {
            $evaluator = app(SmartPlaylistEvaluator::class);
            $songs = $evaluator->evaluate($playlist->rules)->get();
        } else {
            $songs = $playlist->songs;
        }

        return SongResource::collection($songs);
    }
}
```

### Prevent Manual Modification

```php
class PlaylistSongController extends Controller
{
    public function store(AddPlaylistSongsRequest $request, Playlist $playlist)
    {
        if ($playlist->is_smart) {
            return response()->json([
                'error' => [
                    'code' => 'INVALID_OPERATION',
                    'message' => 'Cannot add songs to a smart playlist',
                ],
            ], 400);
        }

        // Add songs...
    }
}
```

## Frontend Integration

### Smart Playlist Editor Component

```typescript
// types
interface RuleGroup {
  logic: 'and' | 'or';
  rules: Rule[];
}

interface Rule {
  field: string;
  operator: string;
  value: string | number | boolean | number[];
}

// store
const playlistsStore = defineStore('playlists', () => {
  async function createSmartPlaylist(name: string, rules: RuleGroup[]) {
    const response = await api.post('/playlists', {
      name,
      is_smart: true,
      rules,
    });
    return response.data;
  }
});
```

### Field Configuration

```typescript
// config/smartPlaylist/fields.ts
export const fields = {
  title: {
    label: 'Title',
    type: 'string',
    operators: ['equals', 'contains', 'starts_with', 'ends_with'],
  },
  year: {
    label: 'Year',
    type: 'number',
    operators: ['equals', 'greater_than', 'less_than', 'between'],
  },
  is_favorite: {
    label: 'Is Favorite',
    type: 'boolean',
    operators: ['equals'],
  },
  tag: {
    label: 'Tag',
    type: 'tag',
    operators: ['has', 'has_any'],
  },
};
```

## Materialization

Smart playlists use a hybrid evaluation strategy combining materialized results with dynamic evaluation.

### Database Schema

The `playlists` table includes:

| Column | Type | Description |
|--------|------|-------------|
| `is_smart` | boolean | Whether this is a smart playlist |
| `rules` | jsonb | Rule groups for smart playlists |
| `rules_updated_at` | timestamp | When rules were last modified |
| `materialized_at` | timestamp | When songs were last materialized |

### Materialization Strategy

Smart playlist songs are stored in the `playlist_song` pivot table (same as regular playlists) for fast retrieval.

**When materialization occurs:**

1. **On creation** - `PlaylistObserver::created()` dispatches `RefreshSmartPlaylistJob`
2. **When rules change** - `PlaylistObserver::updated()` dispatches `RefreshSmartPlaylistJob`
3. **Scheduled refresh** - `RefreshStaleSmartPlaylistsJob` runs hourly for playlists not refreshed in 24 hours
4. **Manual refresh** - `POST /api/v1/playlists/{id}/refresh` queues immediate refresh

### RefreshSmartPlaylistJob

Located at `app/Jobs/RefreshSmartPlaylistJob.php`:

```php
class RefreshSmartPlaylistJob implements ShouldQueue, ShouldBeUnique
{
    public function __construct(
        public Playlist $playlist
    ) {}

    public function handle(SmartPlaylistEvaluator $evaluator): void
    {
        if (!$this->playlist->is_smart) {
            return;
        }

        $user = $this->playlist->user;

        DB::transaction(function () use ($evaluator, $user) {
            // Clear existing materialized songs
            $this->playlist->songs()->detach();

            if (empty($this->playlist->rules)) {
                $this->playlist->update(['materialized_at' => now()]);
                return;
            }

            // Get all matching song IDs
            $matchingSongIds = $evaluator->evaluateDynamic($this->playlist, $user)
                ->pluck('id')
                ->all();

            if (count($matchingSongIds) > 0) {
                // Bulk insert with positions
                $pivotData = [];
                foreach ($matchingSongIds as $index => $songId) {
                    $pivotData[$songId] = [
                        'position' => $index,
                        'added_by' => null,
                        'created_at' => now(),
                    ];
                }
                $this->playlist->songs()->attach($pivotData);
            }

            $this->playlist->update(['materialized_at' => now()]);
        });
    }
}
```

### Incremental Updates

When songs are modified, `UpdateSmartPlaylistsForSongJob` updates materialized playlists incrementally:

```php
// app/Jobs/UpdateSmartPlaylistsForSongJob.php
class UpdateSmartPlaylistsForSongJob implements ShouldQueue, ShouldBeUnique
{
    public function __construct(
        public Song $song,
        public bool $removing = false
    ) {}

    public function handle(SmartPlaylistEvaluator $evaluator): void
    {
    // Only updates already-materialized playlists
    $smartPlaylists = Playlist::smart()
        ->whereNotNull('materialized_at')
        ->get();

    foreach ($smartPlaylists as $playlist) {
        $user = $playlist->user;
        $matches = $evaluator->matches($playlist, $this->song, $user);
        $existsInPlaylist = $playlist->songs()
            ->where('songs.id', $this->song->id)
            ->exists();

        if ($matches && !$existsInPlaylist) {
            // Add song to playlist at end
            $maxPosition = $playlist->songs()->max('playlist_song.position') ?? -1;
            $playlist->songs()->attach($this->song->id, [
                'position' => $maxPosition + 1,
                'added_by' => null,
                'created_at' => now(),
            ]);
        } elseif (!$matches && $existsInPlaylist) {
            // Remove song from playlist
            $playlist->songs()->detach($this->song->id);
        }
    }
    }
}
```

### needsRematerialization()

The `Playlist` model includes a helper method to check if materialization is stale:

```php
public function needsRematerialization(): bool
{
    if (!$this->is_smart) {
        return false;
    }

    // Never materialized
    if ($this->materialized_at === null) {
        return true;
    }

    // Rules were updated after last materialization
    if ($this->rules_updated_at !== null && $this->rules_updated_at > $this->materialized_at) {
        return true;
    }

    return false;
}
```

### Scheduled Refresh

The `RefreshStaleSmartPlaylistsJob` ensures playlists stay current:

```php
// app/Jobs/RefreshStaleSmartPlaylistsJob.php
class RefreshStaleSmartPlaylistsJob implements ShouldQueue
{
    public function __construct(
        public int $staleAfterHours = 24
    ) {}

    public function handle(): void
    {
        $threshold = Carbon::now()->subHours($this->staleAfterHours);

        Playlist::query()
            ->where('is_smart', true)
            ->where(function ($query) use ($threshold) {
                $query->whereNull('materialized_at')
                    ->orWhere('materialized_at', '<', $threshold);
            })
            ->each(function (Playlist $playlist) {
                RefreshSmartPlaylistJob::dispatch($playlist);
            });
    }
}
```

Scheduled in `routes/console.php`:

```php
Schedule::job(new RefreshStaleSmartPlaylistsJob(staleAfterHours: 24))
    ->hourly()
    ->withoutOverlapping();
```

## Performance Considerations

### Eager Loading

All evaluation methods eager load relationships:

```php
$query = Song::query()->with(['artist', 'album', 'genres', 'tags']);
```

### Pagination

The songs endpoint in `PlaylistController` supports pagination with default limit of 75 (min 1, max 500):

```php
// app/Http/Controllers/Api/V1/PlaylistController.php
class PlaylistController extends Controller
{
    public function __construct(
        // ... other dependencies
        private readonly SmartPlaylistEvaluator $smartPlaylistEvaluator,
    ) {}

    public function songs(Request $request, Playlist $playlist): AnonymousResourceCollection
    {
        $this->authorize('view', $playlist);

    $limit = max(1, min($request->integer('limit', 75), 500));
    $offset = max($request->integer('offset', 0), 0);

    if ($playlist->is_smart) {
        // Use materialized results if available and up-to-date
        if ($playlist->materialized_at !== null && !$playlist->needsRematerialization()) {
            $query = $playlist->songs()->with(['artist', 'album', 'genres', 'tags']);
            $total = $query->count();
            $songs = $query->skip($offset)->take($limit)->get();
        } else {
            // Dynamic evaluation with pagination
            $result = $this->smartPlaylistEvaluator->evaluatePaginated(
                $playlist,
                $request->user(),
                $limit,
                $offset
            );
            $songs = $result['songs'];
            $total = $result['total'];
        }
    } else {
        $query = $playlist->songs()->with(['artist', 'album', 'genres', 'tags']);
        $total = $query->count();
        $songs = $query->skip($offset)->take($limit)->get();
    }

    return SongResource::collection($songs)->additional([
        'meta' => [
            'total' => $total,
            'limit' => $limit,
            'offset' => $offset,
            'has_more' => ($offset + $songs->count()) < $total,
        ],
    ]);
    }
}
```

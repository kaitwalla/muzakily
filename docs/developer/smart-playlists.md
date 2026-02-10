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

```php
namespace App\Services;

class SmartPlaylistEvaluator
{
    public function evaluate(array $rules): Builder
    {
        $query = Song::query();

        foreach ($rules as $group) {
            $query = $this->applyGroup($query, $group);
        }

        return $query;
    }

    protected function applyGroup(Builder $query, array $group): Builder
    {
        $logic = $group['logic'] ?? 'and';
        $rules = $group['rules'] ?? [];

        return $query->where(function ($q) use ($logic, $rules) {
            foreach ($rules as $rule) {
                $method = $logic === 'or' ? 'orWhere' : 'where';
                $this->applyRule($q, $rule, $method);
            }
        });
    }

    protected function applyRule(Builder $query, array $rule, string $method): void
    {
        $field = $rule['field'];
        $operator = $rule['operator'];
        $value = $rule['value'];

        $handler = $this->getFieldHandler($field);
        $handler->apply($query, $operator, $value, $method);
    }
}
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

## Performance Considerations

### Eager Loading

```php
public function evaluate(array $rules): Builder
{
    return Song::query()
        ->with(['artist', 'album', 'tags'])
        ->where(...);
}
```

### Caching Results

For frequently accessed smart playlists:

```php
public function getCachedSongs(Playlist $playlist): Collection
{
    if (!$playlist->is_smart) {
        return $playlist->songs;
    }

    return Cache::remember(
        "smart_playlist:{$playlist->id}",
        300, // 5 minutes
        fn () => $this->evaluate($playlist->rules)->get()
    );
}

// Invalidate on library changes
Event::listen(SongCreated::class, function () {
    Cache::tags('smart_playlists')->flush();
});
```

### Limiting Results

```php
public function songs(Playlist $playlist, Request $request)
{
    $limit = $request->input('limit', 100);
    $offset = $request->input('offset', 0);

    $query = $this->evaluator->evaluate($playlist->rules);

    return SongResource::collection(
        $query->skip($offset)->take($limit)->get()
    );
}
```

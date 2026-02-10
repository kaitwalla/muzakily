# Meilisearch Integration

Muzakily uses Meilisearch for fast, typo-tolerant full-text search with PostgreSQL as a fallback.

## Setup

### Docker Configuration

```yaml
services:
  meilisearch:
    image: getmeili/meilisearch:v1.6
    ports:
      - "7700:7700"
    environment:
      MEILI_MASTER_KEY: ${MEILISEARCH_KEY}
    volumes:
      - meilisearch_data:/meili_data
```

### Environment Variables

```env
MEILISEARCH_HOST=http://meilisearch:7700
MEILISEARCH_KEY=your-master-key
SCOUT_DRIVER=meilisearch
```

### Laravel Scout Configuration

```php
// config/scout.php
return [
    'driver' => env('SCOUT_DRIVER', 'meilisearch'),

    'meilisearch' => [
        'host' => env('MEILISEARCH_HOST', 'http://localhost:7700'),
        'key' => env('MEILISEARCH_KEY'),
    ],
];
```

## Searchable Models

### Song Model

```php
use Laravel\Scout\Searchable;

class Song extends Model
{
    use Searchable;

    public function toSearchableArray(): array
    {
        return [
            'id' => $this->id,
            'title' => $this->title,
            'artist_name' => $this->artist_name,
            'album_name' => $this->album_name,
            'genre' => $this->genre,
            'year' => $this->year,
            'created_at' => $this->created_at?->timestamp,
        ];
    }

    public function searchableAs(): string
    {
        return 'songs';
    }
}
```

### Album Model

```php
class Album extends Model
{
    use Searchable;

    public function toSearchableArray(): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'artist_name' => $this->artist?->name,
            'year' => $this->year,
        ];
    }
}
```

### Artist Model

```php
class Artist extends Model
{
    use Searchable;

    public function toSearchableArray(): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
        ];
    }
}
```

## Index Configuration

### Creating Indexes

```php
// app/Console/Commands/ConfigureMeilisearch.php
class ConfigureMeilisearch extends Command
{
    protected $signature = 'meilisearch:configure';

    public function handle(): void
    {
        $client = app(MeilisearchClient::class);

        // Songs index
        $client->index('songs')->updateSettings([
            'searchableAttributes' => [
                'title',
                'artist_name',
                'album_name',
                'genre',
            ],
            'filterableAttributes' => [
                'year',
                'genre',
                'artist_name',
            ],
            'sortableAttributes' => [
                'title',
                'artist_name',
                'year',
                'created_at',
            ],
            'rankingRules' => [
                'words',
                'typo',
                'proximity',
                'attribute',
                'sort',
                'exactness',
            ],
        ]);

        // Albums index
        $client->index('albums')->updateSettings([
            'searchableAttributes' => ['name', 'artist_name'],
            'filterableAttributes' => ['year'],
        ]);

        // Artists index
        $client->index('artists')->updateSettings([
            'searchableAttributes' => ['name'],
        ]);
    }
}
```

## Search Service

### SearchService

```php
namespace App\Services;

class SearchService
{
    public function search(string $query, array $options = []): array
    {
        $type = $options['type'] ?? null;
        $limit = $options['limit'] ?? 10;
        $filters = $options['filters'] ?? [];

        $results = [
            'songs' => ['data' => [], 'total' => 0],
            'albums' => ['data' => [], 'total' => 0],
            'artists' => ['data' => [], 'total' => 0],
        ];

        if (!$type || $type === 'song') {
            $results['songs'] = $this->searchSongs($query, $limit, $filters);
        }

        if (!$type || $type === 'album') {
            $results['albums'] = $this->searchAlbums($query, $limit, $filters);
        }

        if (!$type || $type === 'artist') {
            $results['artists'] = $this->searchArtists($query, $limit);
        }

        return $results;
    }

    protected function searchSongs(string $query, int $limit, array $filters): array
    {
        $builder = Song::search($query)->take($limit);

        if (isset($filters['year'])) {
            $builder->where('year', $filters['year']);
        }

        if (isset($filters['genre'])) {
            $builder->where('genre', $filters['genre']);
        }

        $results = $builder->get();

        return [
            'data' => $results,
            'total' => $builder->count(),
        ];
    }
}
```

## PostgreSQL Fallback

### Fallback Service

```php
class SearchFallbackService
{
    public function search(string $query, array $options = []): array
    {
        // Use PostgreSQL full-text search
        return [
            'songs' => $this->searchSongsPostgres($query, $options),
            'albums' => $this->searchAlbumsPostgres($query, $options),
            'artists' => $this->searchArtistsPostgres($query, $options),
        ];
    }

    protected function searchSongsPostgres(string $query, array $options): array
    {
        $limit = $options['limit'] ?? 10;

        $songs = Song::query()
            ->where(function ($q) use ($query) {
                $q->where('title', 'ilike', "%{$query}%")
                    ->orWhere('artist_name', 'ilike', "%{$query}%")
                    ->orWhere('album_name', 'ilike', "%{$query}%");
            })
            ->limit($limit)
            ->get();

        return [
            'data' => $songs,
            'total' => $songs->count(),
        ];
    }
}
```

### Automatic Fallback

```php
class SearchService
{
    public function __construct(
        protected SearchFallbackService $fallback
    ) {}

    public function search(string $query, array $options = []): array
    {
        try {
            return $this->searchMeilisearch($query, $options);
        } catch (MeilisearchException $e) {
            Log::warning('Meilisearch unavailable, using fallback', [
                'error' => $e->getMessage(),
            ]);

            return $this->fallback->search($query, $options);
        }
    }
}
```

## Indexing

### Initial Index

```bash
docker compose exec app php artisan scout:import "App\Models\Song"
docker compose exec app php artisan scout:import "App\Models\Album"
docker compose exec app php artisan scout:import "App\Models\Artist"
```

### Automatic Indexing

Scout automatically indexes on model events:

```php
// Model events trigger indexing
$song = Song::create([...]); // Automatically indexed
$song->update([...]); // Automatically re-indexed
$song->delete(); // Automatically removed from index
```

### Batch Indexing

For large imports:

```php
Song::withoutSyncingToSearch(function () {
    // Bulk import without indexing each record
    Song::insert($records);
});

// Then index all at once
Song::all()->searchable();
```

### Queue Indexing

For performance, queue index operations:

```php
// config/scout.php
'queue' => true,

// Or per-model
class Song extends Model
{
    public function shouldBeSearchable(): bool
    {
        return true;
    }

    public function searchableUsing()
    {
        return app(EngineManager::class)->engine('meilisearch');
    }
}
```

## API Endpoint

### SearchController

```php
class SearchController extends Controller
{
    public function __invoke(SearchRequest $request, SearchService $search): JsonResponse
    {
        $results = $search->search(
            $request->input('q'),
            [
                'type' => $request->input('type'),
                'limit' => $request->input('limit', 10),
                'filters' => $request->input('filters', []),
            ]
        );

        return response()->json([
            'data' => [
                'songs' => [
                    'data' => SongResource::collection($results['songs']['data']),
                    'total' => $results['songs']['total'],
                ],
                'albums' => [
                    'data' => AlbumResource::collection($results['albums']['data']),
                    'total' => $results['albums']['total'],
                ],
                'artists' => [
                    'data' => ArtistResource::collection($results['artists']['data']),
                    'total' => $results['artists']['total'],
                ],
            ],
        ]);
    }
}
```

## Monitoring

### Health Check

```php
class MeilisearchHealthCheck
{
    public function check(): bool
    {
        try {
            $client = app(MeilisearchClient::class);
            $client->health();
            return true;
        } catch (Exception $e) {
            return false;
        }
    }
}
```

### Stats

```php
// Get index stats
$client->index('songs')->getStats();
// Returns: documents count, indexing status, etc.
```

## Troubleshooting

### Reindex All

```bash
docker compose exec app php artisan scout:flush "App\Models\Song"
docker compose exec app php artisan scout:import "App\Models\Song"
```

### Check Index Status

```bash
curl http://localhost:7700/indexes/songs/stats \
  -H "Authorization: Bearer your-master-key"
```

### View Index Settings

```bash
curl http://localhost:7700/indexes/songs/settings \
  -H "Authorization: Bearer your-master-key"
```

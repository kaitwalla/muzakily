# Contributing

Guidelines for contributing to Muzakily.

## Development Setup

See [Setup Guide](setup.md) for environment setup.

## Code Standards

### PHP

- **PSR-12** coding style
- **PHPStan level 6** - All code must pass static analysis
- **Type declarations** - Use strict types everywhere

```php
declare(strict_types=1);

namespace App\Services;

class ExampleService
{
    public function process(string $input): array
    {
        // Implementation
    }
}
```

### TypeScript

- **Strict mode** enabled
- **ESLint** + **Prettier** for formatting
- **Type everything** - Avoid `any`

```typescript
interface Song {
  id: string;
  title: string;
  artist_name: string;
}

function playSong(song: Song): void {
  // Implementation
}
```

### PostgreSQL

- Use `ilike` for case-insensitive searches (not `like`)
- Use null-safe operators where appropriate
- Handle race conditions with `firstOrCreate`

## Git Workflow

### Branch Naming

```
feature/add-playlist-shuffle
fix/song-streaming-error
refactor/player-store
docs/api-endpoints
```

### Commit Messages

Follow conventional commits:

```
feat: add shuffle button to playlist view
fix: resolve audio player memory leak
docs: update API authentication guide
refactor: extract streaming service
test: add playlist controller tests
```

### Pull Requests

1. Create feature branch from `main`
2. Write tests first (TDD)
3. Implement feature
4. Ensure all tests pass
5. Ensure PHPStan passes
6. Create PR with description
7. Request review

## Testing Requirements

### Before Submitting

```bash
# PHP tests
docker compose exec app php artisan test

# PHPStan
docker compose exec app ./vendor/bin/phpstan analyse --level=6

# Frontend tests
npm run test

# Type check
npm run type-check
```

### Test Coverage

- New features require tests
- Bug fixes should include regression tests
- Aim for >80% coverage

### Test Naming

```php
public function test_user_can_create_playlist(): void
public function test_cannot_add_songs_to_smart_playlist(): void
public function test_returns_404_for_missing_song(): void
```

## Architecture Guidelines

### Controllers

- Keep thin - delegate to services
- One public method per controller (or use resourceful methods)
- Use Form Requests for validation

```php
class PlaylistController extends Controller
{
    public function store(
        CreatePlaylistRequest $request,
        PlaylistService $service
    ): PlaylistResource {
        $playlist = $service->create(
            $request->user(),
            $request->validated()
        );

        return new PlaylistResource($playlist);
    }
}
```

### Services

- Business logic lives here
- Inject dependencies via constructor
- Return domain objects (not responses)

```php
class PlaylistService
{
    public function create(User $user, array $data): Playlist
    {
        return $user->playlists()->create([
            'name' => $data['name'],
            'is_smart' => $data['is_smart'] ?? false,
            'rules' => $data['rules'] ?? null,
        ]);
    }
}
```

### Models

- Define relationships
- Use accessors/mutators for computed properties
- Define scopes for reusable queries

```php
class Song extends Model
{
    public function artist(): BelongsTo
    {
        return $this->belongsTo(Artist::class);
    }

    public function scopeFavorited(Builder $query): Builder
    {
        return $query->whereHas('favorites', fn ($q) =>
            $q->where('user_id', auth()->id())
        );
    }
}
```

### API Resources

- Transform models to JSON
- Control what's exposed
- Include computed fields

```php
class SongResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'title' => $this->title,
            'artist_name' => $this->artist_name,
            'is_favorite' => $this->is_favorite,
            // Don't expose file_path or internal fields
        ];
    }
}
```

## Documentation

### Code Comments

- Write self-documenting code
- Comment "why", not "what"
- PHPDoc for public methods

```php
/**
 * Evaluate smart playlist rules and return matching songs.
 *
 * Rules are evaluated lazily using query builder to avoid
 * loading all songs into memory.
 */
public function evaluate(array $rules): Builder
{
    // Implementation
}
```

### API Documentation

- Update OpenAPI spec for API changes
- Update endpoint docs in `docs/api/endpoints/`

## Review Process

### Reviewer Checklist

- [ ] Tests pass
- [ ] PHPStan passes
- [ ] Code follows standards
- [ ] No security vulnerabilities
- [ ] Documentation updated
- [ ] No breaking changes (or documented)

### Feedback

- Be constructive
- Explain reasoning
- Suggest alternatives

## Releasing

### Version Bumping

Follow semver:

- **MAJOR**: Breaking API changes
- **MINOR**: New features, backward compatible
- **PATCH**: Bug fixes

### Changelog

Update `CHANGELOG.md` with:

```markdown
## [1.2.0] - 2024-01-15

### Added
- Smart playlist shuffle mode
- Album cover lazy loading

### Fixed
- Memory leak in audio player

### Changed
- Improved search relevance
```

## Getting Help

- Open an issue for bugs
- Discussions for questions
- Discord for real-time chat

## License

By contributing, you agree that your contributions will be licensed under the MIT License.

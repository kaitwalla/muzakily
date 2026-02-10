# Testing

Muzakily follows TDD practices with comprehensive test coverage across unit, feature, and contract tests.

## Test Structure

```
tests/
├── Unit/               # Unit tests
│   ├── Models/
│   ├── Services/
│   └── Rules/
├── Feature/            # Feature/integration tests
│   ├── Api/
│   │   └── V1/
│   └── Console/
├── Contracts/          # API contract tests
│   └── Api/
│       └── V1/
└── js/                 # Frontend tests
    ├── components/
    ├── composables/
    └── stores/
```

## Running Tests

### PHP Tests

```bash
# All tests
docker compose exec app php artisan test

# Specific suite
docker compose exec app php artisan test --testsuite=Unit
docker compose exec app php artisan test --testsuite=Feature
docker compose exec app php artisan test --testsuite=Contracts

# Specific file
docker compose exec app php artisan test tests/Feature/Api/V1/SongControllerTest.php

# With coverage
docker compose exec app php artisan test --coverage
```

### Frontend Tests

```bash
# Run tests
npm run test

# Watch mode
npm run test:watch

# With coverage
npm run test:coverage
```

### PHPStan

```bash
docker compose exec app ./vendor/bin/phpstan analyse --level=6
```

## Writing Tests

### Unit Tests

Test individual classes in isolation:

```php
namespace Tests\Unit\Services;

use App\Services\SmartPlaylistEvaluator;
use PHPUnit\Framework\TestCase;

class SmartPlaylistEvaluatorTest extends TestCase
{
    public function test_evaluates_equals_operator(): void
    {
        $evaluator = new SmartPlaylistEvaluator();

        $rules = [
            [
                'logic' => 'and',
                'rules' => [
                    ['field' => 'genre', 'operator' => 'equals', 'value' => 'Rock'],
                ],
            ],
        ];

        $query = $evaluator->evaluate($rules);

        $this->assertStringContainsString(
            'where "genre" = ?',
            $query->toSql()
        );
    }
}
```

### Feature Tests

Test complete features through HTTP:

```php
namespace Tests\Feature\Api\V1;

use App\Models\Artist;
use App\Models\Song;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SongControllerTest extends TestCase
{
    use RefreshDatabase;

    public function test_can_list_songs(): void
    {
        $user = User::factory()->create();
        Song::factory()->count(5)->create();

        $response = $this->actingAs($user)
            ->getJson('/api/v1/songs');

        $response->assertOk()
            ->assertJsonCount(5, 'data')
            ->assertJsonStructure([
                'data' => [
                    '*' => ['id', 'title', 'artist_name', 'album_name'],
                ],
                'links',
                'meta',
            ]);
    }

    public function test_can_filter_by_artist(): void
    {
        $user = User::factory()->create();
        $artist = Artist::factory()->create();
        Song::factory()->count(3)->create(['artist_id' => $artist->id]);
        Song::factory()->count(2)->create();

        $response = $this->actingAs($user)
            ->getJson("/api/v1/songs?artist_id={$artist->id}");

        $response->assertOk()
            ->assertJsonCount(3, 'data');
    }

    public function test_requires_authentication(): void
    {
        $response = $this->getJson('/api/v1/songs');

        $response->assertUnauthorized();
    }
}
```

### Contract Tests

Verify API response structures:

```php
namespace Tests\Contracts\Api\V1;

use App\Models\Song;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SongContractTest extends TestCase
{
    use RefreshDatabase;

    public function test_song_resource_contract(): void
    {
        $user = User::factory()->create();
        $song = Song::factory()->create();

        $response = $this->actingAs($user)
            ->getJson("/api/v1/songs/{$song->id}");

        $response->assertOk()
            ->assertJsonStructure([
                'data' => [
                    'id',
                    'title',
                    'artist_id',
                    'artist_name',
                    'album_id',
                    'album_name',
                    'album_cover',
                    'length',
                    'track',
                    'disc',
                    'year',
                    'genre',
                    'audio_format',
                    'is_favorite',
                    'play_count',
                    'smart_folder',
                    'tags',
                    'created_at',
                ],
            ])
            ->assertJsonPath('data.id', $song->id)
            ->assertJsonPath('data.title', $song->title);
    }

    public function test_pagination_contract(): void
    {
        $user = User::factory()->create();
        Song::factory()->count(100)->create();

        $response = $this->actingAs($user)
            ->getJson('/api/v1/songs?per_page=10');

        $response->assertOk()
            ->assertJsonStructure([
                'data',
                'links' => ['first', 'last', 'prev', 'next'],
                'meta' => [
                    'current_page',
                    'from',
                    'last_page',
                    'per_page',
                    'to',
                    'total',
                ],
            ])
            ->assertJsonCount(10, 'data')
            ->assertJsonPath('meta.total', 100);
    }
}
```

## Factories

### Model Factories

```php
namespace Database\Factories;

use App\Models\Artist;
use App\Models\Album;
use App\Models\Song;
use Illuminate\Database\Eloquent\Factories\Factory;

class SongFactory extends Factory
{
    protected $model = Song::class;

    public function definition(): array
    {
        return [
            'title' => fake()->sentence(3),
            'artist_id' => Artist::factory(),
            'album_id' => Album::factory(),
            'length' => fake()->numberBetween(120, 480),
            'track' => fake()->numberBetween(1, 15),
            'disc' => 1,
            'year' => fake()->numberBetween(1960, 2024),
            'genre' => fake()->randomElement(['Rock', 'Pop', 'Jazz', 'Classical']),
            'audio_format' => fake()->randomElement(['MP3', 'AAC', 'FLAC']),
            'file_path' => 'music/' . fake()->uuid() . '.flac',
            'file_hash' => fake()->sha256(),
        ];
    }

    public function withArtist(Artist $artist): static
    {
        return $this->state(fn () => ['artist_id' => $artist->id]);
    }

    public function favorited(): static
    {
        return $this->afterCreating(function (Song $song) {
            $song->favorites()->create(['user_id' => User::factory()->create()->id]);
        });
    }
}
```

## Frontend Tests

### Component Tests

```typescript
// tests/js/components/AudioPlayer.spec.ts
import { mount } from '@vue/test-utils';
import { createPinia, setActivePinia } from 'pinia';
import { vi } from 'vitest';
import AudioPlayer from '@/components/player/AudioPlayer.vue';
import { usePlayerStore } from '@/stores/player';

describe('AudioPlayer', () => {
  beforeEach(() => {
    setActivePinia(createPinia());
  });

  it('renders play button when paused', () => {
    const wrapper = mount(AudioPlayer);
    const playerStore = usePlayerStore();

    playerStore.isPlaying = false;

    expect(wrapper.find('[data-testid="play-button"]').exists()).toBe(true);
    expect(wrapper.find('[data-testid="pause-button"]').exists()).toBe(false);
  });

  it('toggles play/pause on button click', async () => {
    const wrapper = mount(AudioPlayer);
    const playerStore = usePlayerStore();
    const toggleSpy = vi.spyOn(playerStore, 'togglePlayPause');

    await wrapper.find('[data-testid="play-button"]').trigger('click');

    expect(toggleSpy).toHaveBeenCalled();
  });
});
```

### Store Tests

```typescript
// tests/js/stores/player.spec.ts
import { setActivePinia, createPinia } from 'pinia';
import { usePlayerStore } from '@/stores/player';

describe('PlayerStore', () => {
  beforeEach(() => {
    setActivePinia(createPinia());
  });

  it('adds song to queue', () => {
    const store = usePlayerStore();
    const song = { id: '1', title: 'Test Song' };

    store.addToQueue(song);

    expect(store.queue).toHaveLength(1);
    expect(store.queue[0].song).toEqual(song);
  });

  it('plays next song', () => {
    const store = usePlayerStore();
    store.addToQueue({ id: '1', title: 'Song 1' });
    store.addToQueue({ id: '2', title: 'Song 2' });
    store.currentIndex = 0;

    store.next();

    expect(store.currentIndex).toBe(1);
  });
});
```

## Test Helpers

### Custom Assertions

```php
// tests/TestCase.php
abstract class TestCase extends BaseTestCase
{
    protected function assertApiSuccess($response): void
    {
        $response->assertSuccessful();
        $response->assertJsonStructure(['data']);
    }

    protected function assertApiPaginated($response, int $expectedCount): void
    {
        $response->assertJsonCount($expectedCount, 'data');
        $response->assertJsonStructure([
            'links' => ['first', 'last'],
            'meta' => ['total', 'per_page'],
        ]);
    }
}
```

### Authentication Helper

```php
trait AuthenticatesUser
{
    protected function loginAs(?User $user = null): User
    {
        $user ??= User::factory()->create();
        $this->actingAs($user);
        return $user;
    }

    protected function loginAsAdmin(): User
    {
        return $this->loginAs(User::factory()->admin()->create());
    }
}
```

## CI/CD Integration

### GitHub Actions

```yaml
name: Tests

on: [push, pull_request]

jobs:
  test:
    runs-on: ubuntu-latest

    services:
      postgres:
        image: postgres:15
        env:
          POSTGRES_DB: testing
          POSTGRES_USER: testing
          POSTGRES_PASSWORD: testing
        ports:
          - 5432:5432

    steps:
      - uses: actions/checkout@v4

      - name: Setup PHP
        uses: shivammathur/setup-php@v2
        with:
          php-version: 8.3

      - name: Install dependencies
        run: composer install

      - name: Run PHPStan
        run: ./vendor/bin/phpstan analyse --level=6

      - name: Run tests
        run: php artisan test
        env:
          DB_CONNECTION: pgsql
          DB_HOST: localhost
          DB_DATABASE: testing
          DB_USERNAME: testing
          DB_PASSWORD: testing
```

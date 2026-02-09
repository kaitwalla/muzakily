<?php

declare(strict_types=1);

namespace Tests\Feature\Services;

use App\Models\Album;
use App\Models\Artist;
use App\Models\Genre;
use App\Models\Song;
use App\Models\Tag;
use App\Services\Search\MeilisearchService;
use App\Services\Search\PostgresSearchService;
use App\Services\Search\SearchService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Mockery;
use Mockery\MockInterface;
use Tests\TestCase;

class SearchServiceTest extends TestCase
{
    use RefreshDatabase;

    public function test_search_uses_meilisearch_when_available(): void
    {
        // Create test data first (with collection driver)
        $artist = Artist::factory()->create();
        $album = Album::factory()->create(['artist_id' => $artist->id]);
        Song::factory()->create([
            'title' => 'Test Song',
            'artist_id' => $artist->id,
            'album_id' => $album->id,
        ]);

        // Now set Scout driver to meilisearch and mock the service
        config(['scout.driver' => 'meilisearch']);

        $mock = Mockery::mock(MeilisearchService::class);
        $mock->shouldReceive('isAvailable')->andReturn(true);
        $mock->shouldReceive('search')
            ->with('Test', Mockery::any())
            ->once()
            ->andReturn([
                'songs' => ['data' => [], 'total' => 0],
                'albums' => ['data' => [], 'total' => 0],
                'artists' => ['data' => [], 'total' => 0],
                'meta' => ['query' => 'Test', 'engine' => 'meilisearch'],
            ]);

        $this->app->instance(MeilisearchService::class, $mock);

        /** @var SearchService $service */
        $service = app(SearchService::class);
        $service->search('Test');
    }

    public function test_search_falls_back_to_postgres_when_meilisearch_unavailable(): void
    {
        $artist = Artist::factory()->create(['name' => 'Fallback Artist']);
        $album = Album::factory()->create(['artist_id' => $artist->id]);
        Song::factory()->create([
            'title' => 'Fallback Song',
            'artist_id' => $artist->id,
            'album_id' => $album->id,
        ]);

        $this->mock(MeilisearchService::class, function (MockInterface $mock): void {
            $mock->shouldReceive('isAvailable')->andReturn(false);
        });

        /** @var SearchService $service */
        $service = app(SearchService::class);
        $results = $service->search('Fallback');

        $this->assertArrayHasKey('songs', $results);
        $this->assertArrayHasKey('engine', $results['meta'] ?? []);
        $this->assertEquals('postgresql', $results['meta']['engine'] ?? 'postgresql');
    }

    public function test_postgres_search_finds_songs_by_title(): void
    {
        $artist = Artist::factory()->create();
        $album = Album::factory()->create(['artist_id' => $artist->id]);
        Song::factory()->create([
            'title' => 'Christmas Time',
            'artist_id' => $artist->id,
            'album_id' => $album->id,
        ]);

        /** @var PostgresSearchService $service */
        $service = app(PostgresSearchService::class);
        $results = $service->search('Christmas');

        $this->assertCount(1, $results['songs']['data']);
    }

    public function test_postgres_search_finds_songs_by_artist(): void
    {
        $artist = Artist::factory()->create(['name' => 'Holiday Band']);
        $album = Album::factory()->create(['artist_id' => $artist->id]);
        Song::factory()->create([
            'title' => 'Some Song',
            'artist_name' => 'Holiday Band',
            'artist_id' => $artist->id,
            'album_id' => $album->id,
        ]);

        /** @var PostgresSearchService $service */
        $service = app(PostgresSearchService::class);
        $results = $service->search('Holiday');

        $this->assertCount(1, $results['songs']['data']);
    }

    public function test_postgres_search_finds_albums(): void
    {
        $artist = Artist::factory()->create();
        Album::factory()->create([
            'name' => 'Greatest Hits',
            'artist_id' => $artist->id,
        ]);

        /** @var PostgresSearchService $service */
        $service = app(PostgresSearchService::class);
        $results = $service->search('Greatest');

        $this->assertCount(1, $results['albums']['data']);
    }

    public function test_postgres_search_finds_artists(): void
    {
        Artist::factory()->create(['name' => 'Jazz Masters']);

        /** @var PostgresSearchService $service */
        $service = app(PostgresSearchService::class);
        $results = $service->search('Jazz');

        $this->assertCount(1, $results['artists']['data']);
    }

    public function test_search_with_type_filter(): void
    {
        $artist = Artist::factory()->create(['name' => 'Rock Band']);
        $album = Album::factory()->create([
            'name' => 'Rock Album',
            'artist_id' => $artist->id,
        ]);
        Song::factory()->create([
            'title' => 'Rock Song',
            'artist_id' => $artist->id,
            'album_id' => $album->id,
        ]);

        /** @var PostgresSearchService $service */
        $service = app(PostgresSearchService::class);

        // Search only songs
        $results = $service->search('Rock', ['type' => 'song']);
        $this->assertArrayHasKey('songs', $results);
        $this->assertArrayNotHasKey('albums', $results);
        $this->assertArrayNotHasKey('artists', $results);

        // Search only albums
        $results = $service->search('Rock', ['type' => 'album']);
        $this->assertArrayHasKey('albums', $results);
        $this->assertArrayNotHasKey('songs', $results);

        // Search only artists
        $results = $service->search('Rock', ['type' => 'artist']);
        $this->assertArrayHasKey('artists', $results);
        $this->assertArrayNotHasKey('songs', $results);
    }

    public function test_search_with_year_filter(): void
    {
        $artist = Artist::factory()->create();
        $album = Album::factory()->create(['artist_id' => $artist->id]);

        Song::factory()->create([
            'title' => 'Old Song',
            'year' => 2015,
            'artist_id' => $artist->id,
            'album_id' => $album->id,
        ]);

        Song::factory()->create([
            'title' => 'New Song',
            'year' => 2023,
            'artist_id' => $artist->id,
            'album_id' => $album->id,
        ]);

        /** @var PostgresSearchService $service */
        $service = app(PostgresSearchService::class);
        $results = $service->search('Song', ['filters' => ['year' => 2023]]);

        $this->assertCount(1, $results['songs']['data']);
        $this->assertEquals('New Song', $results['songs']['data'][0]->title);
    }

    public function test_search_with_tag_filter(): void
    {
        $artist = Artist::factory()->create();
        $album = Album::factory()->create(['artist_id' => $artist->id]);
        $tag = Tag::factory()->create(['name' => 'Rock', 'slug' => 'rock']);

        $song1 = Song::factory()->create([
            'title' => 'Tagged Song',
            'artist_id' => $artist->id,
            'album_id' => $album->id,
        ]);
        $song1->tags()->attach($tag->id);

        Song::factory()->create([
            'title' => 'Untagged Song',
            'artist_id' => $artist->id,
            'album_id' => $album->id,
        ]);

        /** @var PostgresSearchService $service */
        $service = app(PostgresSearchService::class);
        $results = $service->search('Song', ['filters' => ['tag' => 'rock']]);

        $this->assertCount(1, $results['songs']['data']);
        $this->assertEquals('Tagged Song', $results['songs']['data'][0]->title);
    }

    public function test_search_with_genre_filter(): void
    {
        $artist = Artist::factory()->create();
        $album = Album::factory()->create(['artist_id' => $artist->id]);
        $genre = Genre::factory()->create(['name' => 'Jazz']);

        $song1 = Song::factory()->create([
            'title' => 'Jazz Song',
            'artist_id' => $artist->id,
            'album_id' => $album->id,
        ]);
        $song1->genres()->attach($genre->id);

        Song::factory()->create([
            'title' => 'Other Song',
            'artist_id' => $artist->id,
            'album_id' => $album->id,
        ]);

        /** @var PostgresSearchService $service */
        $service = app(PostgresSearchService::class);
        $results = $service->search('Song', ['filters' => ['genre' => 'Jazz']]);

        $this->assertCount(1, $results['songs']['data']);
        $this->assertEquals('Jazz Song', $results['songs']['data'][0]->title);
    }

    public function test_search_respects_limit(): void
    {
        $artist = Artist::factory()->create();
        $album = Album::factory()->create(['artist_id' => $artist->id]);
        Song::factory()->count(20)->create([
            'title' => 'Test Song',
            'artist_id' => $artist->id,
            'album_id' => $album->id,
        ]);

        /** @var PostgresSearchService $service */
        $service = app(PostgresSearchService::class);
        $results = $service->search('Test', ['limit' => 5]);

        $this->assertCount(5, $results['songs']['data']);
        $this->assertEquals(20, $results['songs']['total']);
    }

    public function test_search_is_case_insensitive(): void
    {
        $artist = Artist::factory()->create();
        $album = Album::factory()->create(['artist_id' => $artist->id]);
        Song::factory()->create([
            'title' => 'CHRISTMAS Song',
            'artist_id' => $artist->id,
            'album_id' => $album->id,
        ]);

        /** @var PostgresSearchService $service */
        $service = app(PostgresSearchService::class);

        $results1 = $service->search('christmas');
        $results2 = $service->search('CHRISTMAS');
        $results3 = $service->search('Christmas');

        $this->assertCount(1, $results1['songs']['data']);
        $this->assertCount(1, $results2['songs']['data']);
        $this->assertCount(1, $results3['songs']['data']);
    }

    public function test_search_escapes_special_characters(): void
    {
        $artist = Artist::factory()->create();
        $album = Album::factory()->create(['artist_id' => $artist->id]);
        Song::factory()->create([
            'title' => 'Test%Song',
            'artist_id' => $artist->id,
            'album_id' => $album->id,
        ]);

        /** @var PostgresSearchService $service */
        $service = app(PostgresSearchService::class);

        // Should not match everything due to %
        $results = $service->search('%');
        $this->assertCount(1, $results['songs']['data']);
    }

    public function test_search_includes_meta_information(): void
    {
        /** @var PostgresSearchService $service */
        $service = app(PostgresSearchService::class);
        $results = $service->search('test');

        $this->assertArrayHasKey('meta', $results);
        $this->assertArrayHasKey('query', $results['meta']);
        $this->assertArrayHasKey('engine', $results['meta']);
        $this->assertEquals('test', $results['meta']['query']);
        $this->assertEquals('postgresql', $results['meta']['engine']);
    }
}

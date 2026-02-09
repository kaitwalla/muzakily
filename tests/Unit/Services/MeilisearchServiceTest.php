<?php

declare(strict_types=1);

namespace Tests\Unit\Services;

use App\Models\Album;
use App\Models\Artist;
use App\Models\Song;
use App\Services\Search\MeilisearchService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Meilisearch\Client;
use Mockery;
use Mockery\MockInterface;
use Tests\TestCase;

class MeilisearchServiceTest extends TestCase
{
    use RefreshDatabase;

    public function test_search_songs_returns_paginated_results(): void
    {
        $artist = Artist::factory()->create(['name' => 'Test Artist']);
        $album = Album::factory()->create(['name' => 'Test Album', 'artist_id' => $artist->id]);
        Song::factory()->count(5)->create([
            'title' => 'Christmas Song',
            'artist_id' => $artist->id,
            'album_id' => $album->id,
        ]);

        /** @var MeilisearchService $service */
        $service = app(MeilisearchService::class);

        // This will use the collection driver in tests (Scout fallback)
        $results = $service->searchSongs('Christmas', [], 10);

        $this->assertCount(5, $results);
    }

    public function test_search_albums_returns_results(): void
    {
        $artist = Artist::factory()->create();
        Album::factory()->count(3)->create([
            'name' => 'Holiday Album',
            'artist_id' => $artist->id,
        ]);

        /** @var MeilisearchService $service */
        $service = app(MeilisearchService::class);

        $results = $service->searchAlbums('Holiday', 10);

        $this->assertCount(3, $results);
    }

    public function test_search_artists_returns_results(): void
    {
        Artist::factory()->count(2)->create(['name' => 'Jazz Artist']);

        /** @var MeilisearchService $service */
        $service = app(MeilisearchService::class);

        $results = $service->searchArtists('Jazz', 10);

        $this->assertCount(2, $results);
    }

    public function test_search_with_filters(): void
    {
        $artist = Artist::factory()->create();
        $album = Album::factory()->create(['artist_id' => $artist->id]);

        Song::factory()->create([
            'title' => 'Rock Song 2023',
            'year' => 2023,
            'artist_id' => $artist->id,
            'album_id' => $album->id,
        ]);

        Song::factory()->create([
            'title' => 'Rock Song 2020',
            'year' => 2020,
            'artist_id' => $artist->id,
            'album_id' => $album->id,
        ]);

        /** @var MeilisearchService $service */
        $service = app(MeilisearchService::class);

        // Filter by year
        $results = $service->searchSongs('Rock', ['year' => 2023], 10);

        $this->assertCount(1, $results);
        $this->assertEquals(2023, $results->first()->year);
    }

    public function test_global_search_returns_all_types(): void
    {
        $artist = Artist::factory()->create(['name' => 'Christmas Band']);
        $album = Album::factory()->create([
            'name' => 'Christmas Album',
            'artist_id' => $artist->id,
        ]);
        Song::factory()->create([
            'title' => 'Christmas Time',
            'artist_id' => $artist->id,
            'album_id' => $album->id,
        ]);

        /** @var MeilisearchService $service */
        $service = app(MeilisearchService::class);

        $results = $service->search('Christmas');

        $this->assertArrayHasKey('songs', $results);
        $this->assertArrayHasKey('albums', $results);
        $this->assertArrayHasKey('artists', $results);
    }

    public function test_is_available_returns_false_when_meilisearch_down(): void
    {
        // Mock the client to throw an exception
        $this->mock(Client::class, function (MockInterface $mock): void {
            $mock->shouldReceive('isHealthy')->andReturn(false);
        });

        /** @var MeilisearchService $service */
        $service = app(MeilisearchService::class);

        $this->assertFalse($service->isAvailable());
    }

    public function test_searchable_array_includes_required_fields(): void
    {
        $artist = Artist::factory()->create();
        $album = Album::factory()->create(['artist_id' => $artist->id]);
        $song = Song::factory()->create([
            'artist_id' => $artist->id,
            'album_id' => $album->id,
        ]);

        $searchable = $song->toSearchableArray();

        $this->assertArrayHasKey('id', $searchable);
        $this->assertArrayHasKey('title', $searchable);
        $this->assertArrayHasKey('artist_name', $searchable);
        $this->assertArrayHasKey('album_name', $searchable);
        $this->assertArrayHasKey('year', $searchable);
        $this->assertArrayHasKey('audio_format', $searchable);
        $this->assertArrayHasKey('tag_ids', $searchable);
        $this->assertArrayHasKey('genre_ids', $searchable);
    }

    public function test_searchable_array_includes_tag_ids(): void
    {
        $artist = Artist::factory()->create();
        $album = Album::factory()->create(['artist_id' => $artist->id]);
        $song = Song::factory()->create([
            'artist_id' => $artist->id,
            'album_id' => $album->id,
        ]);

        $tag1 = \App\Models\Tag::factory()->create();
        $tag2 = \App\Models\Tag::factory()->create();
        $song->tags()->attach([$tag1->id, $tag2->id]);

        $song->refresh();
        $searchable = $song->toSearchableArray();

        $this->assertContains($tag1->id, $searchable['tag_ids']);
        $this->assertContains($tag2->id, $searchable['tag_ids']);
    }

    public function test_album_searchable_array(): void
    {
        $artist = Artist::factory()->create(['name' => 'Test Artist']);
        $album = Album::factory()->create([
            'artist_id' => $artist->id,
            'name' => 'Test Album',
            'year' => 2023,
        ]);

        $searchable = $album->toSearchableArray();

        $this->assertArrayHasKey('id', $searchable);
        $this->assertArrayHasKey('name', $searchable);
        $this->assertArrayHasKey('artist_name', $searchable);
        $this->assertArrayHasKey('year', $searchable);
        $this->assertEquals('Test Album', $searchable['name']);
        $this->assertEquals('Test Artist', $searchable['artist_name']);
    }

    public function test_artist_searchable_array(): void
    {
        $artist = Artist::factory()->create([
            'name' => 'Test Artist',
            'bio' => 'A great artist biography',
        ]);

        $searchable = $artist->toSearchableArray();

        $this->assertArrayHasKey('id', $searchable);
        $this->assertArrayHasKey('name', $searchable);
        $this->assertArrayHasKey('bio', $searchable);
        $this->assertEquals('Test Artist', $searchable['name']);
        $this->assertEquals('A great artist biography', $searchable['bio']);
    }
}

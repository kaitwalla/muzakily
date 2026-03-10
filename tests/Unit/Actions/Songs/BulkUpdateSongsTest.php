<?php

declare(strict_types=1);

namespace Tests\Unit\Actions\Songs;

use App\Actions\Songs\BulkUpdateSongs;
use App\Models\Song;
use App\Models\Tag;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class BulkUpdateSongsTest extends TestCase
{
    use RefreshDatabase;

    private BulkUpdateSongs $action;

    protected function setUp(): void
    {
        parent::setUp();
        $this->action = new BulkUpdateSongs();
    }

    public function test_updates_song_attributes(): void
    {
        $songs = Song::factory()->count(2)->create(['year' => 2020]);

        $result = $this->action->execute(
            $songs,
            ['year' => 2024],
            null,
            null
        );

        $this->assertCount(2, $result);
        foreach ($result as $song) {
            $this->assertEquals(2024, $song->year);
        }
    }

    public function test_adds_tags_to_songs(): void
    {
        $songs = Song::factory()->count(2)->create();
        $tag = Tag::factory()->create();

        $result = $this->action->execute(
            $songs,
            [],
            [$tag->id],
            null
        );

        foreach ($result as $song) {
            $this->assertTrue($song->tags->contains($tag));
        }
    }

    public function test_removes_tags_from_songs(): void
    {
        $tag = Tag::factory()->create();
        $songs = Song::factory()->count(2)->create();
        foreach ($songs as $song) {
            $song->tags()->attach($tag);
        }

        $result = $this->action->execute(
            $songs,
            [],
            null,
            [$tag->id]
        );

        foreach ($result as $song) {
            $this->assertFalse($song->tags->contains($tag));
        }
    }

    public function test_handles_both_add_and_remove_tags(): void
    {
        $tagToRemove = Tag::factory()->create();
        $tagToAdd = Tag::factory()->create();
        $songs = Song::factory()->count(2)->create();
        foreach ($songs as $song) {
            $song->tags()->attach($tagToRemove);
        }

        $result = $this->action->execute(
            $songs,
            [],
            [$tagToAdd->id],
            [$tagToRemove->id]
        );

        foreach ($result as $song) {
            $this->assertTrue($song->tags->contains($tagToAdd));
            $this->assertFalse($song->tags->contains($tagToRemove));
        }
    }

    public function test_updates_attributes_and_tags_together(): void
    {
        $songs = Song::factory()->count(2)->create(['year' => 2020]);
        $tag = Tag::factory()->create();

        $result = $this->action->execute(
            $songs,
            ['year' => 2024],
            [$tag->id],
            null
        );

        foreach ($result as $song) {
            $this->assertEquals(2024, $song->year);
            $this->assertTrue($song->tags->contains($tag));
        }
    }

    public function test_returns_fresh_models_with_relations(): void
    {
        $songs = Song::factory()->count(2)->create();
        $tag = Tag::factory()->create();

        $result = $this->action->execute(
            $songs,
            [],
            [$tag->id],
            null
        );

        foreach ($result as $song) {
            $this->assertTrue($song->relationLoaded('tags'));
            $this->assertTrue($song->relationLoaded('artist'));
            $this->assertTrue($song->relationLoaded('album'));
            $this->assertTrue($song->relationLoaded('genres'));
        }
    }

    public function test_handles_empty_song_collection(): void
    {
        $songs = Song::query()->whereNull('id')->get();

        $result = $this->action->execute(
            $songs,
            ['year' => 2024],
            null,
            null
        );

        $this->assertCount(0, $result);
    }

    public function test_handles_empty_update_data(): void
    {
        $songs = Song::factory()->count(2)->create(['year' => 2020]);

        $result = $this->action->execute(
            $songs,
            [],
            null,
            null
        );

        foreach ($result as $song) {
            $this->assertEquals(2020, $song->year);
        }
    }

    public function test_does_not_duplicate_existing_tags(): void
    {
        $tag = Tag::factory()->create();
        $song = Song::factory()->create();
        $song->tags()->attach($tag);
        $songs = new \Illuminate\Database\Eloquent\Collection([$song->fresh()]);

        $result = $this->action->execute(
            $songs,
            [],
            [$tag->id],
            null
        );

        $this->assertCount(1, $result->first()->tags);
    }
}

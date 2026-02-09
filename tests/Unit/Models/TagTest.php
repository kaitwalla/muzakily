<?php

declare(strict_types=1);

namespace Tests\Unit\Models;

use App\Models\Tag;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class TagTest extends TestCase
{
    use RefreshDatabase;

    public function test_extracts_top_level_folder_as_tag(): void
    {
        $path = 'Rock/Artist/Album/song.mp3';
        $tagName = Tag::extractFromPath($path);

        $this->assertEquals('Rock', $tagName);
    }

    public function test_extracts_second_level_for_special_folders(): void
    {
        $path = 'Xmas/Contemporary/Artist/song.mp3';
        $tagName = Tag::extractFromPath($path, ['Xmas']);

        $this->assertEquals('Xmas/Contemporary', $tagName);
    }

    public function test_extracts_special_folder_only_when_has_subfolder(): void
    {
        $path = 'Xmas/song.mp3';
        $tagName = Tag::extractFromPath($path, ['Xmas']);

        $this->assertEquals('Xmas', $tagName);
    }

    public function test_returns_null_for_empty_path(): void
    {
        $path = '';
        $tagName = Tag::extractFromPath($path);

        $this->assertNull($tagName);
    }

    public function test_returns_null_for_root_level_file(): void
    {
        $path = 'song.mp3';
        $tagName = Tag::extractFromPath($path);

        $this->assertNull($tagName);
    }

    public function test_find_or_create_from_path_creates_new_tag(): void
    {
        $path = 'Jazz/Artist/Album/song.mp3';

        $tag = Tag::findOrCreateFromPath($path);

        $this->assertNotNull($tag);
        $this->assertEquals('Jazz', $tag->name);
        $this->assertEquals('jazz', $tag->slug);
        $this->assertDatabaseHas('tags', ['name' => 'Jazz', 'slug' => 'jazz']);
    }

    public function test_find_or_create_from_path_returns_existing_tag(): void
    {
        $existingTag = Tag::factory()->create(['name' => 'Rock', 'slug' => 'rock']);
        $path = 'Rock/Artist/Album/song.mp3';

        $tag = Tag::findOrCreateFromPath($path);

        $this->assertEquals($existingTag->id, $tag->id);
        $this->assertDatabaseCount('tags', 1);
    }

    public function test_find_or_create_from_path_creates_parent_for_special_folder(): void
    {
        $path = 'Xmas/Contemporary/Artist/song.mp3';

        $tag = Tag::findOrCreateFromPath($path, ['Xmas']);

        $this->assertNotNull($tag);
        $this->assertEquals('Xmas/Contemporary', $tag->name);
        $this->assertEquals('xmas-contemporary', $tag->slug);
        $this->assertNotNull($tag->parent);
        $this->assertEquals('Xmas', $tag->parent->name);
    }

    public function test_tag_has_parent_relationship(): void
    {
        $parent = Tag::factory()->create(['name' => 'Xmas']);
        $child = Tag::factory()->create(['name' => 'Xmas/Contemporary', 'parent_id' => $parent->id]);

        $this->assertEquals($parent->id, $child->parent->id);
    }

    public function test_tag_has_children_relationship(): void
    {
        $parent = Tag::factory()->create(['name' => 'Xmas']);
        $child1 = Tag::factory()->create(['name' => 'Xmas/Classic', 'parent_id' => $parent->id]);
        $child2 = Tag::factory()->create(['name' => 'Xmas/Contemporary', 'parent_id' => $parent->id]);

        $this->assertCount(2, $parent->children);
        $this->assertTrue($parent->children->contains($child1));
        $this->assertTrue($parent->children->contains($child2));
    }

    public function test_tag_has_songs_relationship(): void
    {
        $tag = Tag::factory()->create();

        $this->assertInstanceOf(\Illuminate\Database\Eloquent\Relations\BelongsToMany::class, $tag->songs());
    }

    public function test_scope_roots_returns_only_top_level_tags(): void
    {
        $parent = Tag::factory()->create(['name' => 'Xmas', 'parent_id' => null]);
        $child = Tag::factory()->create(['name' => 'Xmas/Contemporary', 'parent_id' => $parent->id]);
        $anotherRoot = Tag::factory()->create(['name' => 'Rock', 'parent_id' => null]);

        $roots = Tag::roots()->get();

        $this->assertCount(2, $roots);
        $this->assertTrue($roots->contains($parent));
        $this->assertTrue($roots->contains($anotherRoot));
        $this->assertFalse($roots->contains($child));
    }

    public function test_generates_slug_from_name(): void
    {
        $tag = Tag::factory()->create(['name' => 'Rock & Roll', 'slug' => null]);

        $this->assertEquals('rock-roll', $tag->slug);
    }

    public function test_generates_unique_slug(): void
    {
        Tag::factory()->create(['name' => 'Rock', 'slug' => 'rock']);
        $tag = Tag::factory()->create(['name' => 'Rock', 'slug' => null]);

        $this->assertNotEquals('rock', $tag->slug);
        $this->assertStringStartsWith('rock-', $tag->slug);
    }

    public function test_update_song_count(): void
    {
        $tag = Tag::factory()->create(['song_count' => 0]);
        $songs = \App\Models\Song::factory()->count(3)->create();

        $tag->songs()->attach($songs->pluck('id'));
        $tag->updateSongCount();

        $this->assertEquals(3, $tag->fresh()->song_count);
    }
}

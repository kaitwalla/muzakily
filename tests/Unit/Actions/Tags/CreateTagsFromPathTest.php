<?php

declare(strict_types=1);

namespace Tests\Unit\Actions\Tags;

use App\Actions\Tags\CreateTagsFromPath;
use App\Models\Tag;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CreateTagsFromPathTest extends TestCase
{
    use RefreshDatabase;

    private CreateTagsFromPath $action;

    protected function setUp(): void
    {
        parent::setUp();
        $this->action = new CreateTagsFromPath();
    }

    public function test_creates_tag_from_simple_path(): void
    {
        $tags = $this->action->execute('Rock/Artist/Album/song.mp3');

        $this->assertCount(1, $tags);
        $this->assertEquals('Rock', $tags->first()->name);
    }

    public function test_returns_existing_tag(): void
    {
        $existingTag = Tag::factory()->create(['name' => 'Rock', 'slug' => 'rock']);

        $tags = $this->action->execute('Rock/Artist/Album/song.mp3');

        $this->assertCount(1, $tags);
        $this->assertEquals($existingTag->id, $tags->first()->id);
        $this->assertDatabaseCount('tags', 1);
    }

    public function test_creates_multiple_tags_for_special_folder(): void
    {
        $tags = $this->action->execute('Xmas/Contemporary/song.mp3', ['xmas']);

        $this->assertCount(2, $tags);
        $tagNames = $tags->pluck('name')->toArray();
        $this->assertContains('xmas', $tagNames);
        $this->assertContains('xmas - contemporary', $tagNames);
    }

    public function test_returns_empty_collection_for_root_file(): void
    {
        $tags = $this->action->execute('song.mp3');

        $this->assertCount(0, $tags);
    }

    public function test_returns_empty_collection_for_empty_path(): void
    {
        $tags = $this->action->execute('');

        $this->assertCount(0, $tags);
    }

    public function test_handles_case_insensitive_special_folders(): void
    {
        $tags = $this->action->execute('XMAS/Traditional/song.mp3', ['xmas']);

        $this->assertCount(2, $tags);
        $tagNames = $tags->pluck('name')->toArray();
        $this->assertContains('xmas', $tagNames);
        $this->assertContains('xmas - traditional', $tagNames);
    }

    public function test_reuses_existing_tag_with_different_casing(): void
    {
        Tag::factory()->create(['name' => 'Rock', 'slug' => 'rock']);

        // Create another tag with different casing
        $this->action->execute('ROCK/Artist/song.mp3');

        // Should reuse existing tag
        $this->assertDatabaseCount('tags', 1);
    }

    public function test_special_folder_without_subfolder(): void
    {
        $tags = $this->action->execute('xmas/song.mp3', ['xmas']);

        $this->assertCount(1, $tags);
        $this->assertEquals('xmas', $tags->first()->name);
    }
}

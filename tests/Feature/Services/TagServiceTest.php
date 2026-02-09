<?php

declare(strict_types=1);

namespace Tests\Feature\Services;

use App\Models\Song;
use App\Models\Tag;
use App\Services\Library\TagService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class TagServiceTest extends TestCase
{
    use RefreshDatabase;

    private TagService $tagService;

    protected function setUp(): void
    {
        parent::setUp();
        $this->tagService = app(TagService::class);
    }

    public function test_assign_from_path_creates_and_assigns_tag(): void
    {
        $song = Song::factory()->create(['storage_path' => 'Rock/Artist/Album/song.mp3']);

        $tag = $this->tagService->assignFromPath($song);

        $this->assertNotNull($tag);
        $this->assertEquals('Rock', $tag->name);
        $this->assertTrue($song->tags->contains($tag));
    }

    public function test_assign_from_path_reuses_existing_tag(): void
    {
        $existingTag = Tag::factory()->create(['name' => 'Rock', 'slug' => 'rock']);
        $song = Song::factory()->create(['storage_path' => 'Rock/Artist/Album/song.mp3']);

        $tag = $this->tagService->assignFromPath($song);

        $this->assertEquals($existingTag->id, $tag->id);
        $this->assertDatabaseCount('tags', 1);
    }

    public function test_assign_from_path_creates_hierarchical_tags_for_special_folders(): void
    {
        $song = Song::factory()->create(['storage_path' => 'Xmas/Contemporary/Artist/song.mp3']);

        $tag = $this->tagService->assignFromPath($song);

        $this->assertNotNull($tag);
        $this->assertEquals('Xmas/Contemporary', $tag->name);
        $this->assertNotNull($tag->parent);
        $this->assertEquals('Xmas', $tag->parent->name);
    }

    public function test_assign_from_path_returns_null_for_root_file(): void
    {
        $song = Song::factory()->create(['storage_path' => 'song.mp3']);

        $tag = $this->tagService->assignFromPath($song);

        $this->assertNull($tag);
    }

    public function test_find_or_create_creates_new_tag(): void
    {
        $tag = $this->tagService->findOrCreate('Jazz');

        $this->assertNotNull($tag);
        $this->assertEquals('Jazz', $tag->name);
        $this->assertEquals('jazz', $tag->slug);
        $this->assertDatabaseHas('tags', ['name' => 'Jazz']);
    }

    public function test_find_or_create_returns_existing_tag(): void
    {
        $existing = Tag::factory()->create(['name' => 'Jazz', 'slug' => 'jazz']);

        $tag = $this->tagService->findOrCreate('Jazz');

        $this->assertEquals($existing->id, $tag->id);
    }

    public function test_find_or_create_with_parent(): void
    {
        $parent = Tag::factory()->create(['name' => 'Xmas']);

        $tag = $this->tagService->findOrCreate('Contemporary', $parent);

        $this->assertEquals($parent->id, $tag->parent_id);
    }

    public function test_sync_song_tags_adds_new_tags(): void
    {
        $song = Song::factory()->create();
        $tag1 = Tag::factory()->create();
        $tag2 = Tag::factory()->create();

        $this->tagService->syncSongTags($song, [$tag1->id, $tag2->id]);

        $this->assertCount(2, $song->fresh()->tags);
    }

    public function test_sync_song_tags_removes_old_tags(): void
    {
        $song = Song::factory()->create();
        $oldTag = Tag::factory()->create();
        $newTag = Tag::factory()->create();
        $song->tags()->attach($oldTag);

        $this->tagService->syncSongTags($song, [$newTag->id]);

        $song->refresh();
        $this->assertCount(1, $song->tags);
        $this->assertTrue($song->tags->contains($newTag));
        $this->assertFalse($song->tags->contains($oldTag));
    }

    public function test_get_available_tags_returns_all_tags(): void
    {
        Tag::factory()->count(5)->create();

        $tags = $this->tagService->getAvailableTags();

        $this->assertCount(5, $tags);
    }

    public function test_get_available_tags_includes_song_counts(): void
    {
        $tag = Tag::factory()->create();
        $songs = Song::factory()->count(3)->create();
        $tag->songs()->attach($songs->pluck('id'));
        $tag->updateSongCount();

        $tags = $this->tagService->getAvailableTags();

        $tagWithCount = $tags->firstWhere('id', $tag->id);
        $this->assertEquals(3, $tagWithCount->song_count);
    }

    public function test_merge_tags_moves_songs_to_target(): void
    {
        $sourceTag = Tag::factory()->create();
        $targetTag = Tag::factory()->create();
        $songs = Song::factory()->count(3)->create();
        $sourceTag->songs()->attach($songs->pluck('id'));

        $this->tagService->mergeTags($sourceTag, $targetTag);

        $targetTag->refresh();
        $this->assertCount(3, $targetTag->songs);
        $this->assertDatabaseMissing('tags', ['id' => $sourceTag->id]);
    }

    public function test_merge_tags_preserves_existing_target_songs(): void
    {
        $sourceTag = Tag::factory()->create();
        $targetTag = Tag::factory()->create();
        $sourceSongs = Song::factory()->count(2)->create();
        $targetSongs = Song::factory()->count(3)->create();
        $sourceTag->songs()->attach($sourceSongs->pluck('id'));
        $targetTag->songs()->attach($targetSongs->pluck('id'));

        $this->tagService->mergeTags($sourceTag, $targetTag);

        $targetTag->refresh();
        $this->assertCount(5, $targetTag->songs);
    }

    public function test_delete_tag_does_not_delete_songs(): void
    {
        $tag = Tag::factory()->create();
        $song = Song::factory()->create();
        $tag->songs()->attach($song);

        $tag->delete();

        $this->assertDatabaseHas('songs', ['id' => $song->id]);
        $this->assertDatabaseMissing('song_tag', ['tag_id' => $tag->id]);
    }

    public function test_tag_auto_assignment_marks_as_auto_assigned(): void
    {
        $song = Song::factory()->create(['storage_path' => 'Rock/Artist/Album/song.mp3']);

        $tag = $this->tagService->assignFromPath($song);

        $pivot = $song->tags()->where('tag_id', $tag->id)->first()->pivot;
        $this->assertTrue($pivot->auto_assigned);
    }

    public function test_manual_tag_assignment_marks_as_not_auto_assigned(): void
    {
        $song = Song::factory()->create();
        $tag = Tag::factory()->create();

        $this->tagService->syncSongTags($song, [$tag->id], autoAssigned: false);

        $pivot = $song->fresh()->tags()->where('tag_id', $tag->id)->first()->pivot;
        $this->assertFalse($pivot->auto_assigned);
    }
}

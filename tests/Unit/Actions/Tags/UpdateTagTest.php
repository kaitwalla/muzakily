<?php

declare(strict_types=1);

namespace Tests\Unit\Actions\Tags;

use App\Actions\Tags\UpdateTag;
use App\Exceptions\CircularTagReferenceException;
use App\Models\Tag;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class UpdateTagTest extends TestCase
{
    use RefreshDatabase;

    private UpdateTag $action;

    protected function setUp(): void
    {
        parent::setUp();
        $this->action = new UpdateTag();
    }

    public function test_updates_tag_name(): void
    {
        $tag = Tag::factory()->create(['name' => 'Old Name']);

        $updated = $this->action->execute($tag, ['name' => 'New Name']);

        $this->assertEquals('New Name', $updated->name);
        $this->assertDatabaseHas('tags', ['id' => $tag->id, 'name' => 'New Name']);
    }

    public function test_updates_tag_color(): void
    {
        $tag = Tag::factory()->create(['color' => '#000000']);

        $updated = $this->action->execute($tag, ['color' => '#e74c3c']);

        $this->assertEquals('#e74c3c', $updated->color);
    }

    public function test_updates_tag_auto_assign_pattern(): void
    {
        $tag = Tag::factory()->create(['auto_assign_pattern' => null]);

        $updated = $this->action->execute($tag, ['auto_assign_pattern' => 'xmas/*']);

        $this->assertEquals('xmas/*', $updated->auto_assign_pattern);
    }

    public function test_clears_auto_assign_pattern_with_empty_string(): void
    {
        $tag = Tag::factory()->create(['auto_assign_pattern' => 'xmas/*']);

        $updated = $this->action->execute($tag, ['auto_assign_pattern' => '']);

        $this->assertNull($updated->auto_assign_pattern);
    }

    public function test_updates_parent_id(): void
    {
        $newParent = Tag::factory()->create(['depth' => 1]);
        $tag = Tag::factory()->create(['depth' => 1, 'parent_id' => null]);

        $updated = $this->action->execute($tag, ['parent_id' => $newParent->id]);

        $this->assertEquals($newParent->id, $updated->parent_id);
        $this->assertEquals(2, $updated->depth);
    }

    public function test_removes_parent_when_null(): void
    {
        $parent = Tag::factory()->create(['depth' => 1]);
        $tag = Tag::factory()->create(['depth' => 2, 'parent_id' => $parent->id]);

        $updated = $this->action->execute($tag, ['parent_id' => null]);

        $this->assertNull($updated->parent_id);
        $this->assertEquals(1, $updated->depth);
    }

    public function test_cascades_depth_updates_to_descendants(): void
    {
        // Create a deeper hierarchy to test cascade
        $root = Tag::factory()->create(['depth' => 1, 'parent_id' => null]);
        $level2 = Tag::factory()->create(['depth' => 2, 'parent_id' => $root->id]);
        $child = Tag::factory()->create(['depth' => 3, 'parent_id' => $level2->id]);
        $grandchild = Tag::factory()->create(['depth' => 4, 'parent_id' => $child->id]);

        // Move child directly under root (depth 1) - should change depths
        $this->action->execute($child, ['parent_id' => $root->id]);

        // Child should now be depth 2 (was 3), grandchild should be depth 3 (was 4)
        $this->assertEquals(2, $child->fresh()->depth);
        $this->assertEquals(3, $grandchild->fresh()->depth);
    }

    public function test_throws_exception_when_tag_is_own_parent(): void
    {
        $tag = Tag::factory()->create();

        $this->expectException(CircularTagReferenceException::class);
        $this->expectExceptionMessage('A tag cannot be its own parent.');

        $this->action->execute($tag, ['parent_id' => $tag->id]);
    }

    public function test_throws_exception_when_parent_is_descendant(): void
    {
        $parent = Tag::factory()->create(['depth' => 1]);
        $child = Tag::factory()->create(['depth' => 2, 'parent_id' => $parent->id]);

        $this->expectException(CircularTagReferenceException::class);
        $this->expectExceptionMessage('A tag cannot have a descendant as its parent.');

        $this->action->execute($parent, ['parent_id' => $child->id]);
    }

    public function test_throws_exception_when_parent_does_not_exist(): void
    {
        $tag = Tag::factory()->create();

        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Parent tag does not exist.');

        $this->action->execute($tag, ['parent_id' => 99999]);
    }

    public function test_only_updates_provided_fields(): void
    {
        $tag = Tag::factory()->create([
            'name' => 'Original',
            'color' => '#000000',
            'auto_assign_pattern' => 'test/*',
        ]);

        $updated = $this->action->execute($tag, ['name' => 'Updated']);

        $this->assertEquals('Updated', $updated->name);
        $this->assertEquals('#000000', $updated->color);
        $this->assertEquals('test/*', $updated->auto_assign_pattern);
    }

    public function test_returns_refreshed_tag_with_updated_data(): void
    {
        $tag = Tag::factory()->create(['name' => 'Old']);

        $updated = $this->action->execute($tag, ['name' => 'New']);

        $this->assertEquals('New', $updated->name);
        $this->assertSame($tag, $updated); // refresh() returns the same instance
    }
}

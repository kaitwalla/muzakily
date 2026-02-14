<?php

declare(strict_types=1);

namespace Tests\Unit\Actions\Tags;

use App\Actions\Tags\CreateTag;
use App\Models\Tag;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CreateTagTest extends TestCase
{
    use RefreshDatabase;

    private CreateTag $action;

    protected function setUp(): void
    {
        parent::setUp();
        $this->action = new CreateTag();
    }

    public function test_creates_tag_with_name(): void
    {
        $tag = $this->action->execute(['name' => 'Rock']);

        $this->assertInstanceOf(Tag::class, $tag);
        $this->assertEquals('Rock', $tag->name);
        $this->assertDatabaseHas('tags', ['name' => 'Rock']);
    }

    public function test_creates_tag_with_color(): void
    {
        $tag = $this->action->execute([
            'name' => 'Rock',
            'color' => '#e74c3c',
        ]);

        $this->assertEquals('#e74c3c', $tag->color);
    }

    public function test_generates_default_color_when_not_provided(): void
    {
        $tag = $this->action->execute(['name' => 'Rock']);

        $this->assertNotNull($tag->color);
        $this->assertMatchesRegularExpression('/^#[0-9a-f]{6}$/i', $tag->color);
    }

    public function test_creates_tag_with_parent(): void
    {
        $parent = Tag::factory()->create(['depth' => 1]);

        $tag = $this->action->execute([
            'name' => 'Contemporary',
            'parent_id' => $parent->id,
        ]);

        $this->assertEquals($parent->id, $tag->parent_id);
        $this->assertEquals(2, $tag->depth);
    }

    public function test_creates_root_tag_with_depth_1(): void
    {
        $tag = $this->action->execute(['name' => 'Rock']);

        $this->assertEquals(1, $tag->depth);
        $this->assertNull($tag->parent_id);
    }

    public function test_creates_tag_with_auto_assign_pattern(): void
    {
        $tag = $this->action->execute([
            'name' => 'Xmas',
            'auto_assign_pattern' => 'xmas/*',
        ]);

        $this->assertEquals('xmas/*', $tag->auto_assign_pattern);
    }

    public function test_throws_exception_for_nonexistent_parent(): void
    {
        $this->expectException(ModelNotFoundException::class);

        $this->action->execute([
            'name' => 'Child',
            'parent_id' => 99999,
        ]);
    }

    public function test_generates_slug(): void
    {
        $tag = $this->action->execute(['name' => 'Rock & Roll']);

        $this->assertEquals('rock-roll', $tag->slug);
    }

    public function test_ignores_empty_color(): void
    {
        $tag = $this->action->execute([
            'name' => 'Rock',
            'color' => '',
        ]);

        $this->assertNotEmpty($tag->color);
    }

    public function test_ignores_empty_auto_assign_pattern(): void
    {
        $tag = $this->action->execute([
            'name' => 'Rock',
            'auto_assign_pattern' => '',
        ]);

        $this->assertNull($tag->auto_assign_pattern);
    }
}

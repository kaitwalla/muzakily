<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\Tag;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<Tag>
 */
class TagFactory extends Factory
{
    protected $model = Tag::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $name = fake()->unique()->word();

        return [
            'name' => ucfirst($name),
            'slug' => Str::slug($name),
            'color' => fake()->hexColor(),
            'parent_id' => null,
            'depth' => 1,
            'is_special' => false,
            'song_count' => 0,
            'auto_assign_pattern' => null,
        ];
    }

    /**
     * Create a special folder tag (like Xmas).
     */
    public function special(): static
    {
        return $this->state(fn (array $attributes) => [
            'is_special' => true,
        ]);
    }

    /**
     * Create a child tag with a parent.
     */
    public function withParent(Tag $parent): static
    {
        return $this->state(fn (array $attributes) => [
            'parent_id' => $parent->id,
            'depth' => $parent->depth + 1,
        ]);
    }

    /**
     * Create a tag with a specific color.
     */
    public function withColor(string $color): static
    {
        return $this->state(fn (array $attributes) => [
            'color' => $color,
        ]);
    }

    /**
     * Create a tag with an auto-assign pattern.
     */
    public function withAutoAssign(string $pattern): static
    {
        return $this->state(fn (array $attributes) => [
            'auto_assign_pattern' => $pattern,
        ]);
    }
}

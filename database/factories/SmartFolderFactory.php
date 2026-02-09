<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\SmartFolder;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\SmartFolder>
 */
class SmartFolderFactory extends Factory
{
    protected $model = SmartFolder::class;

    /**
     * @var array<string>
     */
    private static array $folderNames = [
        'Rock', 'Pop', 'Jazz', 'Classical', 'Electronic',
        'Hip-Hop', 'Country', 'Metal', 'Alternative', 'Indie',
        'Xmas', 'Holiday', 'Seasonal', 'Workout', 'Chill',
    ];

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $name = fake()->randomElement(self::$folderNames);

        return [
            'name' => $name,
            'path_prefix' => $name . '_' . fake()->unique()->randomNumber(5),
            'parent_id' => null,
            'depth' => 1,
            'is_special' => in_array($name, ['Xmas', 'Holiday', 'Seasonal'], true),
            'song_count' => fake()->numberBetween(0, 500),
        ];
    }

    /**
     * Create a subfolder.
     */
    public function subfolder(SmartFolder $parent): static
    {
        $name = fake()->word();

        return $this->state(fn (array $attributes) => [
            'name' => $name,
            'path_prefix' => $parent->path_prefix . '/' . $name,
            'parent_id' => $parent->id,
            'depth' => $parent->depth + 1,
            'is_special' => false,
        ]);
    }

    /**
     * Mark as special folder.
     */
    public function special(): static
    {
        return $this->state(fn (array $attributes) => [
            'is_special' => true,
        ]);
    }
}

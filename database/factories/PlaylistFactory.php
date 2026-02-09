<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\Playlist;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\Playlist>
 */
class PlaylistFactory extends Factory
{
    protected $model = Playlist::class;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'user_id' => User::factory(),
            'name' => fake()->sentence(2),
            'description' => fake()->optional(0.5)->sentence(),
            'cover' => fake()->optional(0.3)->imageUrl(300, 300, 'abstract'),
            'is_smart' => false,
            'rules' => null,
        ];
    }

    /**
     * Create a smart playlist.
     */
    public function smart(): static
    {
        return $this->state(fn (array $attributes) => [
            'is_smart' => true,
            'rules' => [
                [
                    'logic' => 'and',
                    'rules' => [
                        [
                            'field' => 'year',
                            'operator' => 'is_greater_than',
                            'value' => 2000,
                        ],
                    ],
                ],
            ],
        ]);
    }

    /**
     * Create a smart playlist with smart folder rule.
     */
    public function smartWithFolder(string $folderPath): static
    {
        return $this->state(fn (array $attributes) => [
            'is_smart' => true,
            'rules' => [
                [
                    'logic' => 'and',
                    'rules' => [
                        [
                            'field' => 'smart_folder',
                            'operator' => 'is',
                            'value' => $folderPath,
                        ],
                    ],
                ],
            ],
        ]);
    }

    /**
     * Create a smart playlist matching folder prefix.
     */
    public function smartWithFolderPrefix(string $prefix): static
    {
        return $this->state(fn (array $attributes) => [
            'is_smart' => true,
            'rules' => [
                [
                    'logic' => 'and',
                    'rules' => [
                        [
                            'field' => 'smart_folder',
                            'operator' => 'begins_with',
                            'value' => $prefix,
                        ],
                    ],
                ],
            ],
        ]);
    }

    /**
     * Indicate that the playlist has a cover.
     */
    public function withCover(): static
    {
        return $this->state(fn (array $attributes) => [
            'cover' => fake()->imageUrl(300, 300, 'abstract'),
        ]);
    }
}

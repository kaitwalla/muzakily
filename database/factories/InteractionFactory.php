<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\Interaction;
use App\Models\Song;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\Interaction>
 */
class InteractionFactory extends Factory
{
    protected $model = Interaction::class;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'user_id' => User::factory(),
            'song_id' => Song::factory(),
            'play_count' => fake()->numberBetween(1, 100),
            'last_played_at' => fake()->dateTimeBetween('-1 year', 'now'),
        ];
    }

    /**
     * Set a high play count.
     */
    public function frequentlyPlayed(): static
    {
        return $this->state(fn (array $attributes) => [
            'play_count' => fake()->numberBetween(50, 500),
            'last_played_at' => fake()->dateTimeBetween('-1 week', 'now'),
        ]);
    }

    /**
     * Set as recently played.
     */
    public function recentlyPlayed(): static
    {
        return $this->state(fn (array $attributes) => [
            'last_played_at' => fake()->dateTimeBetween('-1 day', 'now'),
        ]);
    }
}

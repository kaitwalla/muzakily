<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\Favorite;
use App\Models\Song;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\Favorite>
 */
class FavoriteFactory extends Factory
{
    protected $model = Favorite::class;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'user_id' => User::factory(),
            'favoritable_type' => Song::class,
            'favoritable_id' => Song::factory(),
        ];
    }

    /**
     * Create a favorite for a specific model.
     *
     * @param \Illuminate\Database\Eloquent\Model $model
     */
    public function for($model): static
    {
        return $this->state(fn (array $attributes) => [
            'favoritable_type' => get_class($model),
            'favoritable_id' => $model->getKey(),
        ]);
    }
}

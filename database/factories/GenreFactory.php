<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\Genre;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\Genre>
 */
class GenreFactory extends Factory
{
    protected $model = Genre::class;

    /**
     * @var array<string>
     */
    private static array $genres = [
        'Rock', 'Pop', 'Jazz', 'Blues', 'Classical', 'Electronic',
        'Hip-Hop', 'R&B', 'Country', 'Folk', 'Metal', 'Punk',
        'Alternative', 'Indie', 'Soul', 'Funk', 'Reggae', 'Latin',
        'World', 'New Age', 'Ambient', 'Dance', 'House', 'Techno',
    ];

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $name = fake()->unique()->randomElement(self::$genres);

        return [
            'name' => $name,
            'name_normalized' => mb_strtolower($name),
        ];
    }
}

<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\Album;
use App\Models\Artist;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\Album>
 */
class AlbumFactory extends Factory
{
    protected $model = Album::class;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $name = fake()->sentence(3);

        return [
            'uuid' => fake()->uuid(),
            'artist_id' => Artist::factory(),
            'name' => $name,
            'name_normalized' => mb_strtolower($name),
            'cover' => fake()->optional(0.5)->imageUrl(300, 300, 'abstract'),
            'year' => fake()->optional(0.8)->numberBetween(1960, 2024),
            'musicbrainz_id' => fake()->optional(0.2)->uuid(),
        ];
    }

    /**
     * Indicate that the album has cover art.
     */
    public function withCover(): static
    {
        return $this->state(fn (array $attributes) => [
            'cover' => fake()->imageUrl(300, 300, 'abstract'),
        ]);
    }

    /**
     * Indicate that the album has MusicBrainz metadata.
     */
    public function withMusicBrainz(): static
    {
        return $this->state(fn (array $attributes) => [
            'musicbrainz_id' => fake()->uuid(),
        ]);
    }
}

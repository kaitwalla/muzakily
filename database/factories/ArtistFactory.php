<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\Artist;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\Artist>
 */
class ArtistFactory extends Factory
{
    protected $model = Artist::class;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $name = fake()->name();

        return [
            'uuid' => fake()->uuid(),
            'name' => $name,
            'name_normalized' => mb_strtolower($name),
            'image' => fake()->optional(0.3)->imageUrl(300, 300, 'people'),
            'musicbrainz_id' => fake()->optional(0.2)->uuid(),
            'bio' => fake()->optional(0.3)->paragraphs(2, true),
        ];
    }

    /**
     * Indicate that the artist has MusicBrainz metadata.
     */
    public function withMusicBrainz(): static
    {
        return $this->state(fn (array $attributes) => [
            'musicbrainz_id' => fake()->uuid(),
            'bio' => fake()->paragraphs(3, true),
        ]);
    }
}

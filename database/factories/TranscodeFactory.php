<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\Song;
use App\Models\Transcode;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\Transcode>
 */
class TranscodeFactory extends Factory
{
    protected $model = Transcode::class;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $format = fake()->randomElement(['mp3', 'aac']);
        $bitrate = fake()->randomElement([128, 192, 256, 320]);

        return [
            'song_id' => Song::factory(),
            'format' => $format,
            'bitrate' => $bitrate,
            'storage_key' => fn (array $attributes) => sprintf(
                'transcodes/%s/%s_%d.%s',
                $attributes['song_id'],
                $attributes['format'],
                $attributes['bitrate'],
                $attributes['format']
            ),
            'file_size' => fake()->numberBetween(500_000, 20_000_000),
        ];
    }

    /**
     * Create an MP3 transcode.
     */
    public function mp3(int $bitrate = 256): static
    {
        return $this->state(fn (array $attributes) => [
            'format' => 'mp3',
            'bitrate' => $bitrate,
            'storage_key' => sprintf(
                'transcodes/%s/mp3_%d.mp3',
                $attributes['song_id'] ?? fake()->uuid(),
                $bitrate
            ),
        ]);
    }

    /**
     * Create an AAC transcode.
     */
    public function aac(int $bitrate = 256): static
    {
        return $this->state(fn (array $attributes) => [
            'format' => 'aac',
            'bitrate' => $bitrate,
            'storage_key' => sprintf(
                'transcodes/%s/aac_%d.aac',
                $attributes['song_id'] ?? fake()->uuid(),
                $bitrate
            ),
        ]);
    }
}

<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Enums\AudioFormat;
use App\Models\Album;
use App\Models\Artist;
use App\Models\SmartFolder;
use App\Models\Song;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\Song>
 */
class SongFactory extends Factory
{
    protected $model = Song::class;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $title = fake()->sentence(3);
        $artistName = fake()->name();
        $albumName = fake()->sentence(2);
        $format = fake()->randomElement(AudioFormat::cases());

        return [
            'album_id' => Album::factory(),
            'artist_id' => Artist::factory(),
            'smart_folder_id' => null,
            'title' => $title,
            'title_normalized' => Str::lower(Str::ascii($title)),
            'album_name' => $albumName,
            'artist_name' => $artistName,
            'length' => fake()->randomFloat(2, 60, 600),
            'track' => fake()->optional(0.8)->numberBetween(1, 20),
            'disc' => fake()->numberBetween(1, 3),
            'year' => fake()->optional(0.7)->numberBetween(1960, 2024),
            'lyrics' => fake()->optional(0.2)->paragraphs(4, true),
            'storage_path' => sprintf(
                '%s/%s/%s.%s',
                fake()->word(),
                fake()->word(),
                fake()->uuid(),
                $format->value
            ),
            'file_hash' => fake()->sha256(),
            'file_size' => fake()->numberBetween(1_000_000, 50_000_000),
            'mime_type' => match ($format) {
                AudioFormat::MP3 => 'audio/mpeg',
                AudioFormat::AAC => 'audio/aac',
                AudioFormat::FLAC => 'audio/flac',
            },
            'audio_format' => $format,
            'r2_etag' => fake()->optional()->sha256(),
            'r2_last_modified' => fake()->optional()->dateTimeBetween('-1 year'),
            'musicbrainz_id' => fake()->optional(0.2)->uuid(),
            'mtime' => fake()->unixTime(),
        ];
    }

    /**
     * Set the song format to MP3.
     */
    public function mp3(): static
    {
        return $this->state(fn (array $attributes) => [
            'audio_format' => AudioFormat::MP3,
            'mime_type' => 'audio/mpeg',
            'storage_path' => preg_replace('/\.\w+$/', '.mp3', $attributes['storage_path']),
        ]);
    }

    /**
     * Set the song format to AAC.
     */
    public function aac(): static
    {
        return $this->state(fn (array $attributes) => [
            'audio_format' => AudioFormat::AAC,
            'mime_type' => 'audio/aac',
            'storage_path' => preg_replace('/\.\w+$/', '.aac', $attributes['storage_path']),
        ]);
    }

    /**
     * Set the song format to FLAC.
     */
    public function flac(): static
    {
        return $this->state(fn (array $attributes) => [
            'audio_format' => AudioFormat::FLAC,
            'mime_type' => 'audio/flac',
            'storage_path' => preg_replace('/\.\w+$/', '.flac', $attributes['storage_path']),
        ]);
    }

    /**
     * Assign to a smart folder.
     */
    public function inFolder(SmartFolder $folder): static
    {
        return $this->state(fn (array $attributes) => [
            'smart_folder_id' => $folder->id,
            'storage_path' => $folder->path_prefix . '/' . basename($attributes['storage_path']),
        ]);
    }

    /**
     * Indicate that the song has MusicBrainz metadata.
     */
    public function withMusicBrainz(): static
    {
        return $this->state(fn (array $attributes) => [
            'musicbrainz_id' => fake()->uuid(),
        ]);
    }

    /**
     * Indicate that the song has lyrics.
     */
    public function withLyrics(): static
    {
        return $this->state(fn (array $attributes) => [
            'lyrics' => fake()->paragraphs(5, true),
        ]);
    }
}

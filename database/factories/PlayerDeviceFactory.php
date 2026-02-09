<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\PlayerDevice;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\PlayerDevice>
 */
class PlayerDeviceFactory extends Factory
{
    protected $model = PlayerDevice::class;

    /**
     * @var array<string>
     */
    private static array $deviceNames = [
        'Living Room Speaker', 'Kitchen Display', 'Bedroom Echo',
        'Office Desktop', 'iPhone', 'Android Phone', 'iPad',
        'MacBook Pro', 'Windows PC', 'Smart TV',
    ];

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'id' => 'device_' . fake()->unique()->regexify('[a-z0-9]{12}'),
            'user_id' => User::factory(),
            'name' => fake()->randomElement(self::$deviceNames),
            'type' => fake()->randomElement(['web', 'mobile', 'desktop']),
            'last_seen' => fake()->dateTimeBetween('-1 hour', 'now'),
            'state' => null,
        ];
    }

    /**
     * Set device type to web.
     */
    public function web(): static
    {
        return $this->state(fn (array $attributes) => [
            'type' => 'web',
        ]);
    }

    /**
     * Set device type to mobile.
     */
    public function mobile(): static
    {
        return $this->state(fn (array $attributes) => [
            'type' => 'mobile',
        ]);
    }

    /**
     * Set device type to desktop.
     */
    public function desktop(): static
    {
        return $this->state(fn (array $attributes) => [
            'type' => 'desktop',
        ]);
    }

    /**
     * Set device as currently playing.
     */
    public function playing(string $songId, float $position = 0): static
    {
        return $this->state(fn (array $attributes) => [
            'state' => [
                'is_playing' => true,
                'current_song_id' => $songId,
                'position' => $position,
                'volume' => 0.8,
            ],
        ]);
    }

    /**
     * Set device as offline (not seen recently).
     */
    public function offline(): static
    {
        return $this->state(fn (array $attributes) => [
            'last_seen' => fake()->dateTimeBetween('-1 week', '-2 hours'),
        ]);
    }
}

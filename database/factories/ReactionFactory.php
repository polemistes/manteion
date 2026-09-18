<?php

namespace Database\Factories;

use App\Models\Leaflet;
use App\Models\Reaction;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Reaction>
 */
class ReactionFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'user_id' => User::factory(),
            'reactable_type' => (new Leaflet)->getMorphClass(),
            'reactable_id' => Leaflet::factory(),
            'emoji' => fake()->randomElement(['👍', '❤️', '😂', '🎉']),
        ];
    }
}

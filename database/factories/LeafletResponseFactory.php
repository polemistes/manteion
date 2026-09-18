<?php

namespace Database\Factories;

use App\Models\Leaflet;
use App\Models\LeafletResponse;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<LeafletResponse>
 */
class LeafletResponseFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'leaflet_id' => Leaflet::factory(),
            'user_id' => User::factory(),
            'body' => fake()->sentence(6),
        ];
    }
}

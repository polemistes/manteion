<?php

namespace Database\Factories;

use App\Enums\LeafletVisibility;
use App\Models\Leaflet;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Leaflet>
 */
class LeafletFactory extends Factory
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
            'body' => fake()->sentence(8),
            'visibility' => LeafletVisibility::Private,
        ];
    }

    public function official(): static
    {
        return $this->state(fn (array $attributes) => ['visibility' => LeafletVisibility::Official]);
    }

    public function friends(): static
    {
        return $this->state(fn (array $attributes) => ['visibility' => LeafletVisibility::Friends]);
    }

    public function friendsAndFollowers(): static
    {
        return $this->state(fn (array $attributes) => ['visibility' => LeafletVisibility::FriendsAndFollowers]);
    }

    public function withLink(string $url = 'https://example.com/article'): static
    {
        return $this->state(fn (array $attributes) => ['link_url' => $url]);
    }
}

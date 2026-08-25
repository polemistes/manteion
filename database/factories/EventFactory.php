<?php

namespace Database\Factories;

use App\Enums\EventVisibility;
use App\Models\Event;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Event>
 */
class EventFactory extends Factory
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
            'name' => fake()->sentence(3),
            'place' => fake()->address(),
            'description' => fake()->paragraph(),
            'starts_at' => fake()->dateTimeBetween('+1 day', '+1 month'),
            'ends_at' => null,
            'visibility' => EventVisibility::Private,
            'allow_invitee_invites' => false,
            'allow_invitee_comments' => true,
        ];
    }

    public function public(): static
    {
        return $this->state(fn (array $attributes) => [
            'visibility' => EventVisibility::Public,
        ]);
    }

    public function searchable(): static
    {
        return $this->state(fn (array $attributes) => [
            'visibility' => EventVisibility::Searchable,
            'searchable_paid_at' => now(),
        ]);
    }
}

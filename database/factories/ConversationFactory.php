<?php

namespace Database\Factories;

use App\Enums\ConversationType;
use App\Models\Conversation;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Conversation>
 */
class ConversationFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'type' => ConversationType::Direct,
            'name' => null,
            'creator_id' => User::factory(),
        ];
    }

    public function group(): static
    {
        return $this->state(fn (array $attributes) => [
            'type' => ConversationType::Group,
            'name' => fake()->words(2, true),
        ]);
    }
}

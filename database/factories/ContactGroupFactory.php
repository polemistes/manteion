<?php

namespace Database\Factories;

use App\Enums\ContactGroupType;
use App\Models\ContactGroup;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<ContactGroup>
 */
class ContactGroupFactory extends Factory
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
            'type' => ContactGroupType::Friend,
            'name' => fake()->words(2, true),
        ];
    }

    public function following(): static
    {
        return $this->state(fn (array $attributes) => [
            'type' => ContactGroupType::Following,
        ]);
    }
}

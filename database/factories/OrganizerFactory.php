<?php

namespace Database\Factories;

use App\Enums\SpokespersonRole;
use App\Models\Organizer;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<Organizer>
 */
class OrganizerFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'name' => fake()->company(),
            'handle' => Str::of(fake()->unique()->userName())->replace('.', '_')->toString(),
            'description' => fake()->sentence(),
            'created_by' => User::factory(),
        ];
    }

    /**
     * With this person as its main spokesperson (accepted).
     */
    public function runBy(User $user): static
    {
        return $this->afterCreating(function (Organizer $organizer) use ($user) {
            $organizer->memberships()->create([
                'user_id' => $user->id,
                'role' => SpokespersonRole::Main,
                'accepted_at' => now(),
            ]);
        });
    }

    /**
     * With this person as an accepted spokesperson.
     */
    public function withSpokesperson(User $user): static
    {
        return $this->afterCreating(function (Organizer $organizer) use ($user) {
            $organizer->memberships()->create([
                'user_id' => $user->id,
                'role' => SpokespersonRole::Spokesperson,
                'accepted_at' => now(),
            ]);
        });
    }
}

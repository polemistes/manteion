<?php

namespace Database\Factories;

use App\Enums\EventVisibility;
use App\Models\EventTemplate;
use App\Models\Organizer;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * A person's own template with no cover and no times. A cover on record
 * has no file behind it: a test that reads the file sends a real upload
 * through the controller instead.
 *
 * @extends Factory<EventTemplate>
 */
class EventTemplateFactory extends Factory
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
            'visibility' => EventVisibility::Private,
            'allow_invitee_invites' => false,
            'allow_invitee_comments' => true,
        ];
    }

    /**
     * With clock times: an evening that ends the same day.
     */
    public function withTimes(string $startsTime = '19:00', ?string $endsTime = '21:00', int $endsDaysLater = 0): static
    {
        return $this->state(fn (array $attributes) => [
            'starts_time' => $startsTime,
            'ends_time' => $endsTime,
            'ends_days_later' => $endsDaysLater,
        ]);
    }

    /**
     * Made for an organizer, and so shared by its spokespersons.
     */
    public function forOrganizer(Organizer $organizer): static
    {
        return $this->state(fn (array $attributes) => [
            'organizer_id' => $organizer->id,
        ]);
    }
}

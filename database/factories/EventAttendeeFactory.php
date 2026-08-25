<?php

namespace Database\Factories;

use App\Enums\AttendanceStatus;
use App\Enums\AttendanceVisibility;
use App\Enums\AttendeeSource;
use App\Models\Event;
use App\Models\EventAttendee;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<EventAttendee>
 */
class EventAttendeeFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'event_id' => Event::factory(),
            'user_id' => User::factory(),
            'invited_by_user_id' => null,
            'status' => AttendanceStatus::NoResponse,
            'attendance_visibility' => AttendanceVisibility::Everyone,
            'source' => AttendeeSource::Invited,
        ];
    }

    public function attending(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => AttendanceStatus::Attending,
        ]);
    }

    public function follower(): static
    {
        return $this->state(fn (array $attributes) => [
            'source' => AttendeeSource::Follower,
        ]);
    }

    public function visibility(AttendanceVisibility $visibility): static
    {
        return $this->state(fn (array $attributes) => [
            'attendance_visibility' => $visibility,
        ]);
    }
}

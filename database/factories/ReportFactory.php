<?php

namespace Database\Factories;

use App\Enums\ReportStatus;
use App\Models\EventComment;
use App\Models\Report;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Report>
 */
class ReportFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'reporter_id' => User::factory(),
            'reportable_type' => (new EventComment)->getMorphClass(),
            'reportable_id' => EventComment::factory(),
            'reason' => fake()->sentence(),
            'status' => ReportStatus::Pending,
        ];
    }
}

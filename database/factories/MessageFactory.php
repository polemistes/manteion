<?php

namespace Database\Factories;

use App\Models\Conversation;
use App\Models\Message;
use App\Models\Picture;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Database\Eloquent\Factories\Sequence;

/**
 * @extends Factory<Message>
 */
class MessageFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'conversation_id' => Conversation::factory(),
            'user_id' => User::factory(),
            'body' => fake()->sentence(),
        ];
    }

    /**
     * With pictures on record but no files behind them: enough for shapes
     * and previews. A test that reads the files sends them through the
     * controller instead.
     */
    public function withPictures(int $count = 1, int $width = 1200, int $height = 900): static
    {
        return $this->has(
            Picture::factory()->count($count)->sequence(fn (Sequence $sequence) => ['position' => $sequence->index])->state(['width' => $width, 'height' => $height]),
            'pictures',
        );
    }
}

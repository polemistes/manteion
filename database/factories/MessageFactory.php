<?php

namespace Database\Factories;

use App\Models\Conversation;
use App\Models\Message;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

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
     * With a picture on record but no file behind it: enough for shapes
     * and previews. A test that reads the file sends one through the
     * controller instead.
     */
    public function withImage(int $width = 1200, int $height = 900): static
    {
        return $this->state(fn () => [
            'image_path' => 'messages/'.fake()->uuid().'.jpg',
            'image_width' => $width,
            'image_height' => $height,
        ]);
    }
}

<?php

namespace Database\Factories;

use App\Models\PushSubscription;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<PushSubscription>
 */
class PushSubscriptionFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'subscribable_type' => (new User)->getMorphClass(),
            'subscribable_id' => User::factory(),
            'endpoint' => 'https://push.example.com/send/'.Str::random(40),
            'public_key' => Str::random(87),
            'auth_token' => Str::random(22),
            'content_encoding' => 'aes128gcm',
            'user_agent' => fake()->userAgent(),
        ];
    }
}

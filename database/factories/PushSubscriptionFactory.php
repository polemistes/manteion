<?php

namespace Database\Factories;

use App\Enums\PushKind;
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
            'endpoint' => 'https://fcm.googleapis.com/fcm/send/'.Str::random(40),
            'public_key' => Str::random(87),
            'auth_token' => Str::random(22),
            'content_encoding' => 'aes128gcm',
            'user_agent' => fake()->userAgent(),
        ];
    }

    /**
     * The Android app on a phone: its FCM registration token, no keys.
     */
    public function app(): static
    {
        return $this->state(fn () => [
            'kind' => PushKind::Fcm,
            'endpoint' => Str::random(22).':APA91b'.Str::random(134),
            'public_key' => null,
            'auth_token' => null,
            'content_encoding' => null,
            'user_agent' => 'Mozilla/5.0 (Linux; Android 15; Pixel 8 Build/AP3A; wv) AppleWebKit/537.36 (KHTML, like Gecko) Version/4.0 Chrome/129.0.6668.100 Mobile Safari/537.36 Manteion-Android',
        ]);
    }
}

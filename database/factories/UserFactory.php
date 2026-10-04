<?php

namespace Database\Factories;

use App\Enums\NotificationCategory;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

/**
 * @extends Factory<User>
 */
class UserFactory extends Factory
{
    /**
     * The current password being used by the factory.
     */
    protected static ?string $password;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'name' => fake()->name(),
            'username' => Str::of(fake()->unique()->userName())->replace('.', '_')->toString(),
            'email' => fake()->unique()->safeEmail(),
            'email_verified_at' => now(),
            'password' => static::$password ??= Hash::make('password'),
            'allow_public_follow' => true,
            'show_friends_list' => true,
            'appear_in_friends_lists' => true,
            'searchable' => true,
            'remember_token' => Str::random(10),
        ];
    }

    /**
     * Indicate that the model's email address should be unverified.
     */
    public function unverified(): static
    {
        return $this->state(fn (array $attributes) => [
            'email_verified_at' => null,
        ]);
    }

    /**
     * Indicate that new follows require the user's approval.
     */
    public function privateFollow(): static
    {
        return $this->state(fn (array $attributes) => [
            'allow_public_follow' => false,
        ]);
    }

    /**
     * Indicate that the model should not show its friends list on its profile.
     */
    public function hideFriendsList(): static
    {
        return $this->state(fn (array $attributes) => [
            'show_friends_list' => false,
        ]);
    }

    /**
     * Indicate that the model should not appear in its friends' friends lists.
     */
    public function hideFromFriendsLists(): static
    {
        return $this->state(fn (array $attributes) => [
            'appear_in_friends_lists' => false,
        ]);
    }

    /**
     * Indicate that the model should not be found via contacts search.
     */
    public function unsearchable(): static
    {
        return $this->state(fn (array $attributes) => [
            'searchable' => false,
        ]);
    }

    /**
     * Indicate that the model wants push notifications for the given
     * categories (only messages are on by default).
     */
    public function pushEnabledFor(NotificationCategory ...$categories): static
    {
        return $this->withPushSettings(true, $categories);
    }

    /**
     * Indicate that the model has turned push notifications off for the
     * given categories.
     */
    public function pushDisabledFor(NotificationCategory ...$categories): static
    {
        return $this->withPushSettings(false, $categories);
    }

    /**
     * Store a push setting for each given category on top of whatever earlier
     * states stored, leaving the other categories at their defaults.
     *
     * @param  array<NotificationCategory>  $categories
     */
    private function withPushSettings(bool $enabled, array $categories): static
    {
        return $this->state(function (array $attributes) use ($enabled, $categories) {
            $settings = $attributes['notification_settings'] ?? [];

            foreach ($categories as $category) {
                $settings['push'][$category->value] = $enabled;
            }

            return ['notification_settings' => $settings];
        });
    }
}

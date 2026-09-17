<?php

namespace App\Actions\Fortify;

use App\Enums\ThemeMode;
use App\Enums\ThemeStyle;
use App\Http\Requests\StoreEventRequest;
use App\Models\User;
use App\Services\ImageProcessor;
use Illuminate\Contracts\Auth\MustVerifyEmail;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Laravel\Fortify\Contracts\UpdatesUserProfileInformation;

class UpdateUserProfileInformation implements UpdatesUserProfileInformation
{
    public function __construct(private ImageProcessor $imageProcessor) {}

    /**
     * Validate and update the given user's profile information.
     *
     * @param  array<string, mixed>  $input
     *
     * @throws ValidationException
     */
    public function update(User $user, array $input): void
    {
        Validator::make($input, [
            'name' => ['required', 'string', 'max:255'],

            'username' => [
                'required',
                'string',
                'max:32',
                'alpha_dash',
                Rule::unique('users')->ignore($user->id),
            ],

            'email' => [
                'required',
                'string',
                'email',
                'max:255',
            ],

            'allow_public_follow' => ['required', 'boolean'],
            'show_friends_list' => ['required', 'boolean'],
            'appear_in_friends_lists' => ['required', 'boolean'],
            'searchable' => ['required', 'boolean'],
            'push_notifications_enabled' => ['required', 'boolean'],
            'notify_event_invites' => ['required', 'boolean'],
            'notify_messages' => ['required', 'boolean'],
            'notify_friend_requests' => ['required', 'boolean'],
            'notify_follow_requests' => ['required', 'boolean'],
            'theme_style' => ['required', Rule::enum(ThemeStyle::class)],
            'theme_mode' => ['required', Rule::enum(ThemeMode::class)],
            'bio' => ['nullable', 'string', 'max:1000'],
            'avatar' => ['nullable', 'image', 'mimes:jpg,jpeg,png,webp', 'max:2048', Rule::dimensions()->maxWidth(StoreEventRequest::MAX_UPLOAD_EDGE)->maxHeight(StoreEventRequest::MAX_UPLOAD_EDGE)],
            'remove_avatar' => ['nullable', 'boolean'],
        ])->validateWithBag('updateProfileInformation');

        $sharedFields = [
            'name' => $input['name'],
            'username' => $input['username'],
            'email' => $input['email'],
            'allow_public_follow' => $input['allow_public_follow'],
            'show_friends_list' => $input['show_friends_list'],
            'appear_in_friends_lists' => $input['appear_in_friends_lists'],
            'searchable' => $input['searchable'],
            'push_notifications_enabled' => $input['push_notifications_enabled'],
            'notify_event_invites' => $input['notify_event_invites'],
            'notify_messages' => $input['notify_messages'],
            'notify_friend_requests' => $input['notify_friend_requests'],
            'notify_follow_requests' => $input['notify_follow_requests'],
            'theme_style' => ThemeStyle::from($input['theme_style']),
            'theme_mode' => ThemeMode::from($input['theme_mode']),
            'bio' => $input['bio'] ?? null,
            'avatar_path' => $this->resolveAvatarPath($user, $input),
        ];

        if ($input['email'] !== $user->email &&
            $user instanceof MustVerifyEmail) {
            $this->updateVerifiedUser($user, $sharedFields);
        } else {
            $user->forceFill($sharedFields)->save();
        }
    }

    /**
     * Update the given verified user's profile information.
     *
     * @param  array<string, mixed>  $sharedFields
     */
    protected function updateVerifiedUser(User $user, array $sharedFields): void
    {
        $user->forceFill([
            ...$sharedFields,
            'email_verified_at' => null,
        ])->save();

        $user->sendEmailVerificationNotification();
    }

    /**
     * @param  array<string, mixed>  $input
     */
    private function resolveAvatarPath(User $user, array $input): ?string
    {
        if (($input['avatar'] ?? null) instanceof UploadedFile) {
            if ($user->avatar_path) {
                Storage::disk('public')->delete($user->avatar_path);
            }

            return $this->imageProcessor->store($input['avatar'], 'avatars', ImageProcessor::AVATAR_MAX_EDGE);
        }

        if (! empty($input['remove_avatar'])) {
            if ($user->avatar_path) {
                Storage::disk('public')->delete($user->avatar_path);
            }

            return null;
        }

        return $user->avatar_path;
    }
}

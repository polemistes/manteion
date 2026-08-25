<?php

namespace App\Actions\Fortify;

use App\Models\User;
use Illuminate\Contracts\Auth\MustVerifyEmail;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Laravel\Fortify\Contracts\UpdatesUserProfileInformation;

class UpdateUserProfileInformation implements UpdatesUserProfileInformation
{
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
                Rule::unique('users')->ignore($user->id),
            ],

            'allow_public_follow' => ['required', 'boolean'],
            'bio' => ['nullable', 'string', 'max:1000'],
            'avatar' => ['nullable', 'image', 'mimes:jpg,jpeg,png,webp', 'max:2048'],
            'remove_avatar' => ['nullable', 'boolean'],
        ])->validateWithBag('updateProfileInformation');

        $sharedFields = [
            'name' => $input['name'],
            'username' => $input['username'],
            'email' => $input['email'],
            'allow_public_follow' => $input['allow_public_follow'],
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

            $storedPath = $input['avatar']->store('avatars', 'public');

            return $storedPath !== false ? $storedPath : null;
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

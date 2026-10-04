<?php

namespace App\Actions\Fortify;

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

/**
 * The account details only (name, username, email, bio, photo). Privacy,
 * notifications and appearance are saved by their own settings endpoints,
 * so whatever else the request carries is ignored here.
 */
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

            'bio' => ['nullable', 'string', 'max:1000'],
            'avatar' => ['nullable', 'image', 'mimes:jpg,jpeg,png,webp', 'max:2048', Rule::dimensions()->maxWidth(StoreEventRequest::MAX_UPLOAD_EDGE)->maxHeight(StoreEventRequest::MAX_UPLOAD_EDGE)],
            'remove_avatar' => ['nullable', 'boolean'],
        ])->validateWithBag('updateProfileInformation');

        $sharedFields = [
            'name' => $input['name'],
            'username' => $input['username'],
            'email' => $input['email'],
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

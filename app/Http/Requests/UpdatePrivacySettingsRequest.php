<?php

namespace App\Http\Requests;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

/**
 * Any subset of the privacy switches: the settings page saves each one as
 * soon as it is flipped.
 */
class UpdatePrivacySettingsRequest extends FormRequest
{
    public function authorize(): bool
    {
        // Only ever changes the signed-in user's own settings.
        return true;
    }

    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'allow_public_follow' => ['sometimes', 'boolean:strict'],
            'show_friends_list' => ['sometimes', 'boolean:strict'],
            'appear_in_friends_lists' => ['sometimes', 'boolean:strict'],
            'searchable' => ['sometimes', 'boolean:strict'],
            'share_typing' => ['sometimes', 'boolean:strict'],
            'share_read_receipts' => ['sometimes', 'boolean:strict'],
        ];
    }
}

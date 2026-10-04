<?php

namespace App\Http\Requests;

use App\Enums\NotificationCategory;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Any subset of the notification settings — the push switch of one or more
 * categories, the message-preview choice — since the settings page saves
 * each toggle on its own. Unknown categories are rejected rather than
 * stored.
 */
class UpdateNotificationSettingsRequest extends FormRequest
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
            'push' => ['sometimes', 'bail', 'array', Rule::arrayKeys(NotificationCategory::cases())],
            'push.*' => ['boolean:strict'],
            'message_previews' => ['sometimes', 'boolean:strict'],
        ];
    }
}

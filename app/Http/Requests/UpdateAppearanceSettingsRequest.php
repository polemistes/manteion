<?php

namespace App\Http\Requests;

use App\Enums\ThemeMode;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateAppearanceSettingsRequest extends FormRequest
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
            'theme_mode' => ['required', Rule::enum(ThemeMode::class)],
        ];
    }
}

<?php

namespace App\Http\Requests;

use App\Models\Organizer;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreOrganizerRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        $organizer = $this->route('organizer');
        $organizerId = $organizer instanceof Organizer ? $organizer->id : null;

        return [
            'name' => ['required', 'string', 'max:80'],
            'handle' => ['required', 'string', 'min:2', 'max:40', 'alpha_dash:ascii', Rule::unique('organizers', 'handle')->ignore($organizerId)],
            'description' => ['nullable', 'string', 'max:2000'],
        ];
    }
}

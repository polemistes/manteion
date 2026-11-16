<?php

namespace App\Http\Requests;

use App\Models\Event;
use App\Services\FacebookEventImporter;
use Closure;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

class ImportFacebookEventRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('create', Event::class);
    }

    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'url' => [
                'required',
                'string',
                'max:2048',
                function (string $attribute, mixed $value, Closure $fail): void {
                    if (! app(FacebookEventImporter::class)->isEventLink((string) $value)) {
                        $fail(__('messages.event_import.not_a_facebook_event'));
                    }
                },
            ],
        ];
    }
}

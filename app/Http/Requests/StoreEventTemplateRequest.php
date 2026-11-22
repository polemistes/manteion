<?php

namespace App\Http\Requests;

use App\Http\Requests\Concerns\ValidatesEventTemplates;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreEventTemplateRequest extends FormRequest
{
    use ValidatesEventTemplates;

    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            ...$this->templateRules(),
            'organizer_id' => ['nullable', 'integer', Rule::exists('organizers', 'id')],
        ];
    }
}

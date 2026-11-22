<?php

namespace App\Http\Requests;

use App\Http\Requests\Concerns\ValidatesEventTemplates;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

class UpdateEventTemplateRequest extends FormRequest
{
    use ValidatesEventTemplates;

    public function authorize(): bool
    {
        return $this->user()->can('update', $this->route('event_template'));
    }

    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return $this->templateRules();
    }
}

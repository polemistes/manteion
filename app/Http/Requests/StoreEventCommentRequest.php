<?php

namespace App\Http\Requests;

use App\Http\Requests\Concerns\ValidatesPictures;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

class StoreEventCommentRequest extends FormRequest
{
    use ValidatesPictures;

    public function authorize(): bool
    {
        return $this->user()->can('comment', $this->route('event'));
    }

    /**
     * A comment is text, pictures, or both.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'body' => ['nullable', 'required_without:pictures', 'string', 'max:2000'],
            'as_organizer' => ['sometimes', 'boolean'],
            ...$this->pictureRules(['required_without:body']),
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return $this->pictureMessages();
    }
}

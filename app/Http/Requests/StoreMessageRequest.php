<?php

namespace App\Http\Requests;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreMessageRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('send', $this->route('conversation'));
    }

    /**
     * A message is text, a picture, or both. The picture's limits are an
     * event photo's (see StoreEventRequest::MAX_UPLOAD_EDGE).
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'body' => ['nullable', 'required_without:image', 'string', 'max:4000'],
            'image' => ['nullable', 'required_without:body', 'image', 'mimes:jpg,jpeg,png,webp', 'max:5120', Rule::dimensions()->maxWidth(StoreEventRequest::MAX_UPLOAD_EDGE)->maxHeight(StoreEventRequest::MAX_UPLOAD_EDGE)],
        ];
    }
}

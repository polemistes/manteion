<?php

namespace App\Http\Requests;

use App\Enums\LeafletVisibility;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreLeafletRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * A leaflet carries at most one attachment: an image or a link.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'body' => ['required', 'string', 'max:2000'],
            'visibility' => ['required', Rule::enum(LeafletVisibility::class)],
            'image' => ['nullable', 'image', 'mimes:jpg,jpeg,png,webp', 'max:5120', Rule::dimensions()->maxWidth(StoreEventRequest::MAX_UPLOAD_EDGE)->maxHeight(StoreEventRequest::MAX_UPLOAD_EDGE), 'prohibits:link_url'],
            'link_url' => ['nullable', 'url:http,https', 'max:2048'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'image.prohibits' => __('messages.leaflet.one_attachment'),
        ];
    }
}

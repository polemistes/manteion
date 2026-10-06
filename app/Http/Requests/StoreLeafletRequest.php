<?php

namespace App\Http\Requests;

use App\Enums\LeafletVisibility;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

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
            'organizer_id' => ['nullable', 'integer', Rule::exists('organizers', 'id')],
        ];
    }

    /**
     * An organizer has followers, not friends, so a leaflet from one
     * cannot be for friends only.
     *
     * @return array<int, callable>
     */
    public function after(): array
    {
        return [
            function (Validator $validator) {
                if ($this->filled('organizer_id') && $this->input('visibility') === LeafletVisibility::Friends->value) {
                    $validator->errors()->add('visibility', __('messages.leaflet.organizer_visibility'));
                }
            },
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

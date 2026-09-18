<?php

namespace App\Http\Requests;

use App\Enums\LeafletVisibility;
use App\Models\Leaflet;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class UpdateLeafletRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('update', $this->route('leaflet'));
    }

    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'body' => ['required', 'string', 'max:2000'],
            'visibility' => ['required', Rule::enum(LeafletVisibility::class)],
            'image' => ['nullable', 'image', 'mimes:jpg,jpeg,png,webp', 'max:5120', Rule::dimensions()->maxWidth(StoreEventRequest::MAX_UPLOAD_EDGE)->maxHeight(StoreEventRequest::MAX_UPLOAD_EDGE), 'prohibits:link_url'],
            'remove_image' => ['sometimes', 'boolean'],
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

    /**
     * A link may only be added when the existing image is being removed
     * (or was never there), so a leaflet never ends up with both.
     *
     * @return array<int, callable>
     */
    public function after(): array
    {
        return [
            function (Validator $validator) {
                $leaflet = $this->route('leaflet');
                $keepsImage = $leaflet instanceof Leaflet && $leaflet->image_path !== null && ! $this->boolean('remove_image');

                if ($keepsImage && $this->filled('link_url')) {
                    $validator->errors()->add('link_url', __('messages.leaflet.one_attachment'));
                }
            },
        ];
    }
}

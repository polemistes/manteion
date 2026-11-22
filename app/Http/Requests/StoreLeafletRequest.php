<?php

namespace App\Http\Requests;

use App\Enums\LeafletVisibility;
use App\Http\Requests\Concerns\ValidatesPictures;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class StoreLeafletRequest extends FormRequest
{
    use ValidatesPictures;

    public function authorize(): bool
    {
        return true;
    }

    /**
     * A leaflet carries pictures or a link, not both.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'body' => ['required', 'string', 'max:2000'],
            'visibility' => ['required', Rule::enum(LeafletVisibility::class)],
            ...$this->pictureRules(['prohibits:link_url']),
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
            ...$this->pictureMessages(),
            'pictures.prohibits' => __('messages.leaflet.one_attachment'),
        ];
    }
}

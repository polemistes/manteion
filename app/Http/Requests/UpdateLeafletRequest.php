<?php

namespace App\Http\Requests;

use App\Enums\LeafletVisibility;
use App\Http\Requests\Concerns\ValidatesPictures;
use App\Models\Leaflet;
use App\Models\Picture;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class UpdateLeafletRequest extends FormRequest
{
    use ValidatesPictures;

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
            ...$this->pictureRules(['prohibits:link_url']),
            // The pictures already on it that go.
            'remove_pictures' => ['sometimes', 'array'],
            'remove_pictures.*' => ['integer'],
            'link_url' => ['nullable', 'url:http,https', 'max:2048'],
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

    /**
     * A link may only be added when the pictures already there are all
     * being removed (or there were none), so a leaflet never ends up with
     * both; and the pictures kept and added together stay within the most
     * a leaflet may carry.
     *
     * @return array<int, callable>
     */
    public function after(): array
    {
        return [
            function (Validator $validator) {
                $leaflet = $this->route('leaflet');
                $kept = $leaflet instanceof Leaflet
                    ? $leaflet->pictures()->whereNotIn('id', array_map(intval(...), (array) $this->input('remove_pictures', [])))->count()
                    : 0;

                if ($kept > 0 && $this->filled('link_url')) {
                    $validator->errors()->add('link_url', __('messages.leaflet.one_attachment'));
                }

                if ($kept + count($this->pictures()) > Picture::MAX_PER_POST) {
                    $validator->errors()->add('pictures', trans_choice('messages.pictures.too_many', Picture::MAX_PER_POST));
                }

                // An organizer has followers, not friends.
                if ($leaflet instanceof Leaflet && $leaflet->organizer_id !== null && $this->input('visibility') === LeafletVisibility::Friends->value) {
                    $validator->errors()->add('visibility', __('messages.leaflet.organizer_visibility'));
                }
            },
        ];
    }
}

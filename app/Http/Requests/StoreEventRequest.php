<?php

namespace App\Http\Requests;

use App\Enums\EventTimeZoneMode;
use App\Enums\EventVisibility;
use App\Http\Requests\Concerns\ExplainsEventErrors;
use App\Http\Requests\Concerns\NormalizesEventTimes;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreEventRequest extends FormRequest
{
    use ExplainsEventErrors;
    use NormalizesEventTimes;

    /**
     * Decoding an image costs about four bytes of memory a pixel, so an
     * upload is capped before it is decoded at all.
     */
    public const MAX_UPLOAD_EDGE = 5000;

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
            'name' => ['required', 'string', 'max:255'],
            'organizer_id' => ['nullable', 'integer', Rule::exists('organizers', 'id')],
            'place' => ['nullable', 'string', 'max:255'],
            'latitude' => ['nullable', 'numeric', 'between:-90,90'],
            'longitude' => ['nullable', 'numeric', 'between:-180,180'],
            'city' => ['nullable', 'string', 'max:255'],
            'country' => ['nullable', 'string', 'max:255'],
            'country_code' => ['nullable', 'string', 'size:2', 'alpha:ascii'],
            // Optional: "Dinner at mine" needs no more words.
            'description' => ['nullable', 'string', 'max:5000'],
            'starts_at' => ['required', 'date'],
            'ends_at' => ['nullable', 'date', 'after:starts_at'],
            'timezone' => ['nullable', 'required_if:timezone_mode,'.EventTimeZoneMode::Chosen->value, 'string', 'max:64', 'timezone:all_with_bc'],
            'timezone_mode' => ['nullable', Rule::enum(EventTimeZoneMode::class)],
            'visibility' => ['required', Rule::in([EventVisibility::Private->value, EventVisibility::Public->value])],
            'allow_invitee_invites' => ['required', 'boolean'],
            'allow_invitee_comments' => ['required', 'boolean'],
            'tag_ids' => ['array', 'max:50'],
            'tag_ids.*' => ['integer', 'exists:tags,id'],
            'image' => ['nullable', 'image', 'mimes:jpg,jpeg,png,webp', 'max:5120', Rule::dimensions()->maxWidth(self::MAX_UPLOAD_EDGE)->maxHeight(self::MAX_UPLOAD_EDGE)],
            'image_fit' => ['nullable', 'string', 'in:cover,contain'],
            // The template or earlier event whose cover the new event copies.
            'cover_from' => ['nullable', 'string', 'regex:/^(template|event):[1-9][0-9]{0,17}$/'],
        ];
    }
}

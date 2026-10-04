<?php

namespace App\Http\Requests;

use App\Enums\EventVisibility;
use App\Http\Requests\Concerns\NormalizesEventTimes;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreEventRequest extends FormRequest
{
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
            'place' => ['nullable', 'string', 'max:255'],
            'latitude' => ['nullable', 'numeric', 'between:-90,90'],
            'longitude' => ['nullable', 'numeric', 'between:-180,180'],
            'city' => ['nullable', 'string', 'max:255'],
            'country' => ['nullable', 'string', 'max:255'],
            'description' => ['required', 'string', 'max:5000'],
            'starts_at' => ['required', 'date'],
            'ends_at' => ['nullable', 'date', 'after:starts_at'],
            'visibility' => ['required', Rule::in([EventVisibility::Private->value, EventVisibility::Public->value])],
            'allow_invitee_invites' => ['required', 'boolean'],
            'allow_invitee_comments' => ['required', 'boolean'],
            'tag_ids' => ['array', 'max:50'],
            'tag_ids.*' => ['integer', 'exists:tags,id'],
            'image' => ['nullable', 'image', 'mimes:jpg,jpeg,png,webp', 'max:5120', Rule::dimensions()->maxWidth(self::MAX_UPLOAD_EDGE)->maxHeight(self::MAX_UPLOAD_EDGE)],
            'image_fit' => ['nullable', 'string', 'in:cover,contain'],
        ];
    }
}

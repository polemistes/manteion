<?php

namespace App\Http\Requests;

use App\Enums\EventVisibility;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateEventRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('update', $this->route('event'));
    }

    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:255'],
            'place' => ['required', 'string', 'max:255'],
            'description' => ['required', 'string', 'max:5000'],
            'starts_at' => ['required', 'date'],
            'ends_at' => ['nullable', 'date', 'after:starts_at'],
            'visibility' => ['required', Rule::in([EventVisibility::Private->value, EventVisibility::Public->value])],
            'allow_invitee_invites' => ['required', 'boolean'],
            'allow_invitee_comments' => ['required', 'boolean'],
            'tags' => ['array', 'max:10'],
            'tags.*' => ['string', 'max:50'],
            'images' => ['array', 'max:6'],
            'images.*' => ['image', 'mimes:jpg,jpeg,png,webp', 'max:5120'],
        ];
    }
}

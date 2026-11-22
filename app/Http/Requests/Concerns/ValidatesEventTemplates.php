<?php

namespace App\Http\Requests\Concerns;

use App\Enums\EventTimeZoneMode;
use App\Enums\EventVisibility;
use App\Http\Requests\StoreEventRequest;
use App\Models\EventTemplate;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Validation\Rule;

/**
 * What a template may hold: an event's fields with only the name required,
 * and clock times in place of the start and end.
 */
trait ValidatesEventTemplates
{
    /**
     * A time field sent empty means no time; one with seconds ("19:00:00",
     * as some browsers write it) loses them.
     */
    protected function prepareForValidation(): void
    {
        foreach (['starts_time', 'ends_time'] as $key) {
            $value = $this->input($key);

            if (is_string($value) && preg_match('/^(\d{2}:\d{2}):\d{2}$/', $value, $match) === 1) {
                $this->merge([$key => $match[1]]);
            }
        }
    }

    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    protected function templateRules(): array
    {
        return [
            'name' => ['required', 'string', 'max:255'],
            'place' => ['nullable', 'string', 'max:255'],
            'latitude' => ['nullable', 'numeric', 'between:-90,90'],
            'longitude' => ['nullable', 'numeric', 'between:-180,180'],
            'city' => ['nullable', 'string', 'max:255'],
            'country' => ['nullable', 'string', 'max:255'],
            'country_code' => ['nullable', 'string', 'size:2', 'alpha:ascii'],
            'description' => ['nullable', 'string', 'max:5000'],
            // An end is counted from a start.
            'starts_time' => ['nullable', 'required_with:ends_time', 'date_format:H:i'],
            'ends_time' => ['nullable', 'date_format:H:i'],
            'ends_days_later' => ['nullable', 'integer', 'min:0', 'max:'.EventTemplate::MAX_DAYS_LATER],
            'timezone' => ['nullable', 'required_if:timezone_mode,'.EventTimeZoneMode::Chosen->value, 'string', 'max:64', 'timezone:all_with_bc'],
            'timezone_mode' => ['nullable', Rule::enum(EventTimeZoneMode::class)],
            'visibility' => ['required', Rule::in([EventVisibility::Private->value, EventVisibility::Public->value])],
            'allow_invitee_invites' => ['required', 'boolean'],
            'allow_invitee_comments' => ['required', 'boolean'],
            'tag_ids' => ['array', 'max:50'],
            'tag_ids.*' => ['integer', 'exists:tags,id'],
            'image' => ['nullable', 'image', 'mimes:jpg,jpeg,png,webp', 'max:5120', Rule::dimensions()->maxWidth(StoreEventRequest::MAX_UPLOAD_EDGE)->maxHeight(StoreEventRequest::MAX_UPLOAD_EDGE)],
            'image_fit' => ['nullable', 'string', 'in:cover,contain'],
            'remove_image' => ['nullable', 'boolean'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'name.required' => __('messages.event_template.name_required'),
            'starts_time.required_with' => __('messages.event_template.end_needs_start'),
            'image.max' => __('messages.event.image_too_large'),
        ];
    }
}

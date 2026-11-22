<?php

namespace App\Http\Requests\Concerns;

/**
 * Says what is wrong with an event in the host's own words: the framework's
 * own messages name the fields as the code does ("The ends at field must be
 * a date after starts at.").
 */
trait ExplainsEventErrors
{
    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'name.required' => __('messages.event.name_required'),
            'description.required' => __('messages.event.description_required'),
            'starts_at.required' => __('messages.event.starts_required'),
            'starts_at.date' => __('messages.event.starts_required'),
            'ends_at.after' => __('messages.event.ends_after_start'),
            'image.max' => __('messages.event.image_too_large'),
        ];
    }
}

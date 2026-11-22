<?php

namespace App\Http\Requests\Concerns;

use App\Http\Requests\StoreEventRequest;
use App\Models\Picture;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Http\UploadedFile;
use Illuminate\Validation\Rule;

/**
 * The pictures sent with a message, a leaflet or an event comment: up to
 * Picture::MAX_PER_POST of them, each within an event photo's limits (see
 * StoreEventRequest::MAX_UPLOAD_EDGE).
 */
trait ValidatesPictures
{
    /**
     * @param  array<int, string>  $also  Further rules for the list itself.
     * @return array<string, array<int, ValidationRule|string>>
     */
    protected function pictureRules(array $also = []): array
    {
        return [
            'pictures' => ['nullable', 'array', 'max:'.Picture::MAX_PER_POST, ...$also],
            'pictures.*' => $this->onePictureRules(),
        ];
    }

    /**
     * @return array<int, ValidationRule|string>
     */
    protected function onePictureRules(): array
    {
        return ['image', 'mimes:jpg,jpeg,png,webp', 'max:5120', Rule::dimensions()->maxWidth(StoreEventRequest::MAX_UPLOAD_EDGE)->maxHeight(StoreEventRequest::MAX_UPLOAD_EDGE)];
    }

    /**
     * @return array<string, string>
     */
    protected function pictureMessages(): array
    {
        return [
            'pictures.max' => trans_choice('messages.pictures.too_many', Picture::MAX_PER_POST),
            'pictures.*.image' => __('messages.pictures.not_a_picture'),
            'pictures.*.mimes' => __('messages.pictures.not_a_picture'),
            'pictures.*.max' => __('messages.pictures.too_large'),
            'pictures.*.dimensions' => __('messages.pictures.too_large'),
        ];
    }

    /**
     * The uploads, in the order they were sent.
     *
     * @return array<int, UploadedFile>
     */
    public function pictures(): array
    {
        $files = $this->file('pictures');

        if ($files === null) {
            return [];
        }

        return is_array($files) ? array_values($files) : [$files];
    }
}

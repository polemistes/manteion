<?php

namespace App\Http\Requests;

use App\Http\Requests\Concerns\ValidatesPictures;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Http\UploadedFile;

class StoreMessageRequest extends FormRequest
{
    use ValidatesPictures {
        pictures as sentPictures;
    }

    public function authorize(): bool
    {
        return $this->user()->can('send', $this->route('conversation'));
    }

    /**
     * A message is text, pictures, or both.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'body' => ['nullable', 'required_without_all:pictures,image', 'string', 'max:4000'],
            ...$this->pictureRules(['required_without_all:body,image']),
            // A page loaded before pictures came in several sends its one so.
            'image' => ['nullable', ...$this->onePictureRules()],
        ];
    }

    /**
     * @return array<int, UploadedFile>
     */
    public function pictures(): array
    {
        $legacy = $this->file('image');

        return [...$this->sentPictures(), ...($legacy instanceof UploadedFile ? [$legacy] : [])];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return $this->pictureMessages();
    }
}

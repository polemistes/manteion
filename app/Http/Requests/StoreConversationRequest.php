<?php

namespace App\Http\Requests;

use App\Models\User;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Validator;

class StoreConversationRequest extends FormRequest
{
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
            'user_ids' => ['required', 'array', 'min:1'],
            'user_ids.*' => ['integer', 'exists:users,id'],
            'name' => ['nullable', 'string', 'max:255'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'user_ids.*.exists' => __('messages.conversation.user_missing'),
        ];
    }

    /**
     * @return array<int, callable>
     */
    public function after(): array
    {
        return [
            function (Validator $validator) {
                if ($validator->errors()->has('user_ids') || $validator->errors()->has('user_ids.*')) {
                    return;
                }

                $nonFriends = User::query()
                    ->whereIn('id', $this->input('user_ids', []))
                    ->get()
                    ->reject(fn (User $candidate) => $this->user()->isFriendsWith($candidate));

                if ($nonFriends->isNotEmpty()) {
                    $validator->errors()->add('user_ids', __('messages.conversation.friends_only'));
                }
            },
        ];
    }
}

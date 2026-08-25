<?php

namespace App\Http\Requests;

use App\Models\User;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Validator;

class StoreConversationParticipantRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('addParticipant', $this->route('conversation'));
    }

    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'user_id' => ['required', 'integer', 'exists:users,id'],
        ];
    }

    /**
     * @return array<int, callable>
     */
    public function after(): array
    {
        return [
            function (Validator $validator) {
                if ($validator->errors()->has('user_id')) {
                    return;
                }

                $candidate = User::find($this->integer('user_id'));

                if ($candidate === null || ! $this->user()->isFriendsWith($candidate)) {
                    $validator->errors()->add('user_id', __('messages.conversation_participant.friends_only'));
                }
            },
        ];
    }
}

<?php

namespace App\Http\Requests;

use App\Models\User;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Validator;

class StoreEventInvitationRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('invite', $this->route('event'));
    }

    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'user_ids' => ['required', 'array', 'min:1'],
            'user_ids.*' => ['integer', 'exists:users,id'],
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

                $nonFriendIds = User::query()
                    ->whereIn('id', $this->input('user_ids', []))
                    ->get()
                    ->reject(fn (User $candidate) => $this->user()->isFriendsWith($candidate));

                if ($nonFriendIds->isNotEmpty()) {
                    $validator->errors()->add('user_ids', __('messages.event_invitation.friends_only'));
                }
            },
        ];
    }
}

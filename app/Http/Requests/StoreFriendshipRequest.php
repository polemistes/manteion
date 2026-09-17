<?php

namespace App\Http\Requests;

use App\Enums\FriendshipStatus;
use App\Models\Friendship;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class StoreFriendshipRequest extends FormRequest
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
            'user_id' => ['required', 'integer', Rule::exists('users', 'id'), Rule::notIn([$this->user()->getKey()])],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'user_id.not_in' => __('messages.friendship.cannot_request_self'),
        ];
    }

    /**
     * @return array<int, callable>
     */
    public function after(): array
    {
        return [
            function (Validator $validator) {
                // A declined request is still open from the requester's point
                // of view, so it blocks a repeat in the same direction. In the
                // other direction it does not: the person who declined may
                // still reach out themselves (see FriendshipController::store).
                $alreadyRelated = Friendship::query()
                    ->where(fn ($query) => $query
                        ->where('requester_id', $this->user()->getKey())
                        ->where('addressee_id', $this->integer('user_id')))
                    ->orWhere(fn ($query) => $query
                        ->where('requester_id', $this->integer('user_id'))
                        ->where('addressee_id', $this->user()->getKey())
                        ->whereIn('status', [FriendshipStatus::Pending, FriendshipStatus::Accepted]))
                    ->exists();

                if ($alreadyRelated) {
                    $validator->errors()->add('user_id', __('messages.friendship.already_related'));
                }
            },
        ];
    }
}

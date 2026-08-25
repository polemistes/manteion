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
                $alreadyRelated = Friendship::query()
                    ->whereIn('status', [FriendshipStatus::Pending, FriendshipStatus::Accepted])
                    ->where(fn ($query) => $query
                        ->where('requester_id', $this->user()->getKey())
                        ->where('addressee_id', $this->integer('user_id')))
                    ->orWhere(fn ($query) => $query
                        ->where('requester_id', $this->integer('user_id'))
                        ->where('addressee_id', $this->user()->getKey()))
                    ->exists();

                if ($alreadyRelated) {
                    $validator->errors()->add('user_id', __('messages.friendship.already_related'));
                }
            },
        ];
    }
}

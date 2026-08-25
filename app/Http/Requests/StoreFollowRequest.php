<?php

namespace App\Http\Requests;

use App\Models\Follow;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class StoreFollowRequest extends FormRequest
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
            'user_id.not_in' => __('messages.follow.cannot_follow_self'),
        ];
    }

    /**
     * @return array<int, callable>
     */
    public function after(): array
    {
        return [
            function (Validator $validator) {
                $alreadyFollowing = Follow::query()
                    ->where('follower_id', $this->user()->getKey())
                    ->where('followed_id', $this->integer('user_id'))
                    ->exists();

                if ($alreadyFollowing) {
                    $validator->errors()->add('user_id', __('messages.follow.already_following'));
                }
            },
        ];
    }
}

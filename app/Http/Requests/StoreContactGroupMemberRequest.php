<?php

namespace App\Http\Requests;

use App\Enums\ContactGroupType;
use App\Models\ContactGroup;
use App\Models\User;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class StoreContactGroupMemberRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('update', $this->route('contact_group'));
    }

    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'member_user_id' => ['required', 'integer', Rule::exists('users', 'id')],
        ];
    }

    /**
     * @return array<int, callable>
     */
    public function after(): array
    {
        return [
            function (Validator $validator) {
                if ($validator->errors()->has('member_user_id')) {
                    return;
                }

                /** @var ContactGroup $group */
                $group = $this->route('contact_group');
                $member = User::find($this->integer('member_user_id'));

                $isEligible = match ($group->type) {
                    ContactGroupType::Friend => $this->user()->isFriendsWith($member),
                    ContactGroupType::Following => $this->user()->isFollowing($member),
                };

                if (! $isEligible) {
                    $validator->errors()->add('member_user_id', match ($group->type) {
                        ContactGroupType::Friend => __('messages.contact_group_member.not_a_friend'),
                        ContactGroupType::Following => __('messages.contact_group_member.not_followed'),
                    });
                }
            },
        ];
    }
}

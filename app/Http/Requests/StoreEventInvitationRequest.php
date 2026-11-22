<?php

namespace App\Http\Requests;

use App\Models\Event;
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

                // A spokesperson may also invite the people who follow the
                // organizer hosting the event: they asked to hear from it.
                /** @var Event $event */
                $event = $this->route('event');
                $followerIds = $event->organizer_id !== null && $this->user()->organizerIds()->contains($event->organizer_id)
                    ? $event->organizer->followers()->pluck('users.id')
                    : collect();

                $nonFriendIds = User::query()
                    ->whereIn('id', $this->input('user_ids', []))
                    ->get()
                    ->reject(fn (User $candidate) => $this->user()->isFriendsWith($candidate) || $followerIds->contains($candidate->id));

                if ($nonFriendIds->isNotEmpty()) {
                    $validator->errors()->add('user_ids', __('messages.event_invitation.friends_only'));
                }
            },
        ];
    }
}

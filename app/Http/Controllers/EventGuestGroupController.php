<?php

namespace App\Http\Controllers;

use App\Enums\ContactGroupType;
use App\Http\Requests\StoreEventGuestGroupRequest;
use App\Models\Event;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class EventGuestGroupController extends Controller
{
    /**
     * Keeps an event's guest list as a group of friends, to invite all at
     * once to the next event. A group of friends holds friends only (and
     * only they can be invited), so guests who are not the host's friends
     * are left out.
     */
    public function __invoke(StoreEventGuestGroupRequest $request, Event $event): RedirectResponse
    {
        $user = $request->user();
        $memberIds = $event->friendGuestIdsOf($user);

        if ($memberIds->isEmpty()) {
            throw ValidationException::withMessages(['name' => __('messages.event_guest_group.no_friends')]);
        }

        DB::transaction(function () use ($request, $user, $memberIds) {
            $group = $user->contactGroups()->create([
                'name' => $request->validated('name'),
                'type' => ContactGroupType::Friend,
            ]);

            $group->members()->sync($memberIds);
        });

        return back();
    }
}

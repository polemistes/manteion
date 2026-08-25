<?php

namespace App\Http\Controllers;

use App\Enums\AttendeeSource;
use App\Http\Requests\StoreEventInvitationRequest;
use App\Models\Event;
use App\Models\EventAttendee;
use App\Notifications\InvitedToEvent;
use Illuminate\Http\RedirectResponse;

class EventInvitationController extends Controller
{
    public function store(StoreEventInvitationRequest $request, Event $event): RedirectResponse
    {
        $inviter = $request->user();

        foreach ($request->input('user_ids') as $userId) {
            $attendee = EventAttendee::query()->firstOrCreate(
                ['event_id' => $event->id, 'user_id' => $userId],
                ['invited_by_user_id' => $inviter->id, 'source' => AttendeeSource::Invited]
            );

            if ($attendee->wasRecentlyCreated) {
                $attendee->user->notify(new InvitedToEvent($event, $inviter));
            }
        }

        return back();
    }
}

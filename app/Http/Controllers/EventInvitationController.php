<?php

namespace App\Http\Controllers;

use App\Enums\AttendeeSource;
use App\Events\EventActivity;
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
        $invitedAnyone = false;

        foreach ($request->input('user_ids') as $userId) {
            $attendee = EventAttendee::query()->firstOrCreate(
                ['event_id' => $event->id, 'user_id' => $userId],
                ['invited_by_user_id' => $inviter->id, 'source' => AttendeeSource::Invited]
            );

            if ($attendee->wasRecentlyCreated) {
                $invitedAnyone = true;

                $attendee->user->notify(
                    (new InvitedToEvent($event, $inviter))->locale($attendee->user->locale ?? 'en')
                );
            }
        }

        if ($invitedAnyone) {
            broadcast(new EventActivity($event->id, 'invitation'))->toOthers();
        }

        return back();
    }
}

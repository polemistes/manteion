<?php

namespace App\Http\Controllers;

use App\Enums\AttendeeSource;
use App\Events\EventActivity;
use App\Http\Requests\UpdateEventAttendanceRequest;
use App\Models\Event;
use App\Models\EventAttendee;
use Illuminate\Http\RedirectResponse;

class EventAttendanceController extends Controller
{
    public function update(UpdateEventAttendanceRequest $request, Event $event): RedirectResponse
    {
        $attendee = EventAttendee::query()->firstOrNew(
            ['event_id' => $event->id, 'user_id' => $request->user()->id],
            ['source' => AttendeeSource::Follower]
        );

        $attendee->fill($request->safe()->only(['status', 'attendance_visibility']));
        $attendee->save();

        broadcast(new EventActivity($event->id, 'attendance'))->toOthers();

        return back();
    }
}

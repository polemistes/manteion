<?php

namespace App\Http\Controllers;

use App\Enums\AttendeeSource;
use App\Http\Requests\StoreEventCommentRequest;
use App\Models\Event;
use App\Models\EventAttendee;
use App\Models\EventComment;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Gate;

class EventCommentController extends Controller
{
    public function store(StoreEventCommentRequest $request, Event $event): RedirectResponse
    {
        $user = $request->user();

        if (! $user->is($event->owner)) {
            EventAttendee::query()->firstOrCreate(
                ['event_id' => $event->id, 'user_id' => $user->id],
                ['source' => AttendeeSource::Follower]
            );
        }

        $event->comments()->create([
            'user_id' => $user->id,
            'body' => $request->validated('body'),
        ]);

        return back();
    }

    public function destroy(Event $event, EventComment $comment): RedirectResponse
    {
        Gate::authorize('delete', $comment);

        $comment->delete();

        return back();
    }
}

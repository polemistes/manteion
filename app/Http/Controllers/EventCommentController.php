<?php

namespace App\Http\Controllers;

use App\Enums\ReportStatus;
use App\Events\EventActivity;
use App\Http\Requests\StoreEventCommentRequest;
use App\Models\Event;
use App\Models\EventComment;
use App\Notifications\NewEventComment;
use App\Services\ActivityFeed;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;

class EventCommentController extends Controller
{
    public function store(StoreEventCommentRequest $request, Event $event): RedirectResponse
    {
        $comment = $event->comments()->create([
            'user_id' => $request->user()->id,
            'body' => $request->validated('body'),
        ]);

        // The host hears about everyone's comments but their own.
        if ($comment->user_id !== $event->user_id) {
            $event->owner->notify((new NewEventComment($comment))->locale($event->owner->locale ?? 'en'));
        }

        broadcast(new EventActivity($event->id, 'comment'))->toOthers();

        return back();
    }

    public function destroy(Request $request, Event $event, EventComment $comment): RedirectResponse
    {
        Gate::authorize('delete', $comment);

        if ($request->user()->isStaff() && $request->boolean('leave_placeholder')) {
            $comment->update(['body' => __('messages.moderation.deleted_placeholder'), 'is_deleted' => true]);
        } else {
            $comment->delete();
        }

        $comment->reports()->where('status', ReportStatus::Pending)->update([
            'status' => ReportStatus::Resolved,
            'resolved_by' => $request->user()->id,
            'resolved_at' => now(),
        ]);

        // The host's notification quotes the comment; it goes with it.
        ActivityFeed::withdraw($event->owner, NewEventComment::class, 'comment_id', $comment->id);

        broadcast(new EventActivity($event->id, 'comment'))->toOthers();

        return back();
    }
}

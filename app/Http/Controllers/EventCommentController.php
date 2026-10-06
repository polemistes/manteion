<?php

namespace App\Http\Controllers;

use App\Enums\ReportStatus;
use App\Events\EventActivity;
use App\Http\Requests\StoreEventCommentRequest;
use App\Models\Event;
use App\Models\EventComment;
use App\Models\User;
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

        // The hosts hear about everyone's comments but their own: the person
        // who made the event, or every spokesperson of the organizer.
        $event->hosts()
            ->reject(fn (User $host) => $host->id === $comment->user_id)
            ->each(fn (User $host) => $host->notify((new NewEventComment($comment))->locale($host->locale ?? 'en')));

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

        // The hosts' notifications quote the comment; they go with it.
        $event->hosts()->each(fn (User $host) => ActivityFeed::withdraw($host, NewEventComment::class, 'comment_id', $comment->id));

        broadcast(new EventActivity($event->id, 'comment'))->toOthers();

        return back();
    }
}

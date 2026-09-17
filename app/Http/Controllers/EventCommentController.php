<?php

namespace App\Http\Controllers;

use App\Enums\ReportStatus;
use App\Http\Requests\StoreEventCommentRequest;
use App\Models\Event;
use App\Models\EventComment;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;

class EventCommentController extends Controller
{
    public function store(StoreEventCommentRequest $request, Event $event): RedirectResponse
    {
        $event->comments()->create([
            'user_id' => $request->user()->id,
            'body' => $request->validated('body'),
        ]);

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

        return back();
    }
}

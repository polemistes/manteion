<?php

namespace App\Http\Controllers;

use App\Enums\ReportStatus;
use App\Http\Requests\StoreMessageRequest;
use App\Models\Conversation;
use App\Models\ConversationParticipant;
use App\Models\Message;
use App\Notifications\NewMessageReceived;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;

class ConversationMessageController extends Controller
{
    public function store(StoreMessageRequest $request, Conversation $conversation): RedirectResponse
    {
        $message = $conversation->messages()->create([
            'user_id' => $request->user()->id,
            'body' => $request->validated('body'),
        ]);

        $conversation->participantFor($request->user())?->update(['last_read_at' => now()]);

        $conversation->participants
            ->reject(fn (ConversationParticipant $participant) => $participant->user_id === $request->user()->id)
            ->each(fn (ConversationParticipant $participant) => $participant->user->notify(
                (new NewMessageReceived($message))->locale($participant->user->locale ?? 'en')
            ));

        return back();
    }

    public function destroy(Request $request, Conversation $conversation, Message $message): RedirectResponse
    {
        Gate::authorize('delete', $message);

        $body = $request->user()->isStaff() && $request->boolean('leave_placeholder')
            ? __('messages.moderation.deleted_placeholder')
            : '';

        $message->update(['body' => $body, 'is_deleted' => true]);

        $message->reports()->where('status', ReportStatus::Pending)->update([
            'status' => ReportStatus::Resolved,
            'resolved_by' => $request->user()->id,
            'resolved_at' => now(),
        ]);

        return back();
    }
}

<?php

namespace App\Http\Controllers;

use App\Enums\ReportStatus;
use App\Http\Requests\StoreMessageRequest;
use App\Models\Conversation;
use App\Models\ConversationParticipant;
use App\Models\Message;
use App\Notifications\NewMessageReceived;
use App\Services\MessageSummary;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;

class ConversationMessageController extends Controller
{
    /**
     * Messages shown per page: the newest on first load, then this many
     * more each time the reader asks for older ones.
     */
    public const PAGE_SIZE = 50;

    /**
     * Messages older than the given one, for the conversation page to
     * prepend. Plain JSON rather than an Inertia visit, so loading history
     * neither replaces the newest messages nor fights the page's polling.
     */
    public function index(Request $request, Conversation $conversation): JsonResponse
    {
        Gate::authorize('view', $conversation);

        $request->validate(['before' => ['required', 'integer']]);

        $messages = $conversation->messages()
            ->with(['sender', 'reactions'])
            ->where('id', '<', $request->integer('before'))
            ->latest('id')
            ->limit(self::PAGE_SIZE)
            ->get();

        $oldestId = $messages->min('id');

        return response()->json([
            'messages' => $messages->sortBy('id')->values()->map(fn (Message $message) => MessageSummary::for($message, $request->user())),
            'has_older' => $oldestId !== null && $conversation->messages()->where('id', '<', $oldestId)->exists(),
        ]);
    }

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

<?php

namespace App\Http\Controllers;

use App\Enums\AdminAlert;
use App\Enums\ReportStatus;
use App\Events\BadgesUpdated;
use App\Events\IncomingMessage;
use App\Events\MessageSent;
use App\Events\MessageUpdated;
use App\Http\Requests\StoreMessageRequest;
use App\Models\Conversation;
use App\Models\ConversationParticipant;
use App\Models\Message;
use App\Models\Reaction;
use App\Models\User;
use App\Notifications\NewMessageReceived;
use App\Services\AdminAlerts;
use App\Services\MessageSummary;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Gate;

class ConversationMessageController extends Controller
{
    /**
     * Messages shown per page: the newest on first load, then this many
     * more each time the reader asks for older ones.
     */
    public const PAGE_SIZE = 50;

    /**
     * The most messages one catch-up request returns. A thread that missed
     * more asks again from the last one it got.
     */
    public const CATCH_UP_LIMIT = 200;

    /**
     * A page of messages as plain JSON, for the open thread: `before` an id
     * for older history to prepend, or `after` an id for whatever arrived
     * while its connection was down (oldest first, so the thread can append
     * them in order).
     */
    public function index(Request $request, Conversation $conversation): JsonResponse
    {
        Gate::authorize('view', $conversation);

        $request->validate([
            'before' => ['required_without:after', 'prohibits:after', 'integer'],
            'after' => ['required_without:before', 'integer'],
        ]);

        $query = $conversation->messages()->with(['sender', 'reactions', 'seenBy']);

        if ($request->filled('after')) {
            $messages = $query
                ->where('id', '>', $request->integer('after'))
                ->oldest('id')
                ->limit(self::CATCH_UP_LIMIT)
                ->get();

            return response()->json([
                'messages' => $messages->map(fn (Message $message) => MessageSummary::for($message, $request->user())),
            ]);
        }

        $messages = $query
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

    /**
     * Save the message and tell everyone: the open thread over the
     * conversation channel, every recipient who has not muted it wherever
     * they are in the app (and their badges), and each recipient's devices
     * by push unless they are reading along. Sending also marks the
     * conversation read for the sender.
     */
    public function store(StoreMessageRequest $request, Conversation $conversation): JsonResponse|RedirectResponse
    {
        $sender = $request->user();
        $conversation->loadMissing('participants.user');

        $message = $conversation->messages()->create([
            'user_id' => $sender->id,
            'body' => $request->validated('body'),
        ]);
        $message->setRelation('conversation', $conversation)
            ->setRelation('sender', $sender)
            ->setRelation('reactions', (new Reaction)->newCollection())
            ->setRelation('seenBy', (new User)->newCollection());

        $conversation->participantFor($sender)?->markRead();

        AdminAlerts::send(AdminAlert::MessageSent, $sender, [
            'sender' => $sender->name,
            'conversation' => $conversation->titleFor($sender),
            'type' => $conversation->type->value,
        ], route('messages.show', $conversation));

        broadcast(new MessageSent($message))->toOthers();

        $conversation->participants
            ->reject(fn (ConversationParticipant $participant) => $participant->user_id === $sender->id)
            ->each(function (ConversationParticipant $recipient) use ($message) {
                if (! $recipient->isMuted()) {
                    IncomingMessage::dispatch($recipient->user_id, $message);
                    BadgesUpdated::dispatch($recipient->user_id);
                }

                $recipient->user->notify((new NewMessageReceived($message))->locale($recipient->user->locale ?? 'en'));
            });

        BadgesUpdated::dispatch($sender->id);

        if ($request->wantsJson()) {
            return response()->json(['message' => MessageSummary::for($message, $sender)], 201);
        }

        return back();
    }

    public function destroy(Request $request, Conversation $conversation, Message $message): RedirectResponse|Response
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

        broadcast(new MessageUpdated($message))->toOthers();

        if ($request->wantsJson()) {
            return response()->noContent();
        }

        return back();
    }
}

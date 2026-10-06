<?php

namespace App\Http\Controllers;

use App\Events\MessageUpdated;
use App\Models\Conversation;
use App\Models\Message;
use App\Services\MessageSummary;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class MessageSeenController extends Controller
{
    /**
     * The reader marks someone else's message as seen, on purpose. Unlike
     * the automatic "Seen", which the privacy switches govern, this is a
     * deliberate reply, so it always shows: under the message, to everyone
     * in the conversation, live. Marking twice changes nothing.
     */
    public function store(Request $request, Conversation $conversation, Message $message): JsonResponse
    {
        $user = $request->user();

        // Staff may read a conversation for moderation; this is taking part.
        abort_unless($conversation->isVisibleTo($user), 403);
        abort_unless($message->conversation_id === $conversation->id, 404);
        abort_if($message->user_id === $user->id || $message->is_deleted, 403);

        $message->seenBy()->syncWithoutDetaching([$user->id => ['created_at' => now()]]);
        $message->load(['sender', 'reactions', 'seenBy']);

        broadcast(new MessageUpdated($message))->toOthers();

        return response()->json(['message' => MessageSummary::for($message, $user)]);
    }
}

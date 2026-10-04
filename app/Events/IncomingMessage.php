<?php

namespace App\Events;

use App\Models\Message;
use App\Models\User;
use App\Services\MessageSummary;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcastNow;
use Illuminate\Contracts\Broadcasting\ShouldRescue;
use Illuminate\Foundation\Events\Dispatchable;

/**
 * A new message, told to one recipient wherever they are in the app (not
 * only in the open thread), for in-app toasts, sounds and the tab title.
 * Sent only to participants who have not muted the conversation.
 *
 * Rescued: an unreachable WebSocket server is logged instead of failing
 * the send.
 */
class IncomingMessage implements ShouldBroadcastNow, ShouldRescue
{
    use Dispatchable, InteractsWithSockets;

    public function __construct(
        public int $recipientId,
        public Message $message,
    ) {}

    /**
     * @return array<int, PrivateChannel>
     */
    public function broadcastOn(): array
    {
        return [new PrivateChannel("App.Models.User.{$this->recipientId}")];
    }

    public function broadcastAs(): string
    {
        return 'message.received';
    }

    /**
     * The conversation's title is the recipient's, e.g. a group without a
     * name is called after its other members.
     *
     * @return array{conversation_id: int, conversation_type: string, conversation_title: string, message_id: int, sender: array{id: int, name: string, avatar_url: string|null}, preview: string}
     */
    public function broadcastWith(): array
    {
        $conversation = $this->message->conversation;
        $sender = $this->message->sender;
        $recipient = $conversation->participants->firstWhere('user_id', $this->recipientId)->user
            ?? User::query()->findOrFail($this->recipientId);

        return [
            'conversation_id' => $conversation->id,
            'conversation_type' => $conversation->type->value,
            'conversation_title' => $conversation->titleFor($recipient),
            'message_id' => $this->message->id,
            'sender' => [
                'id' => $sender->id,
                'name' => $sender->name,
                'avatar_url' => $sender->avatar_url,
            ],
            'preview' => MessageSummary::preview($this->message) ?? '',
        ];
    }
}

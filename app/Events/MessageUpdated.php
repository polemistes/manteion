<?php

namespace App\Events;

use App\Models\Message;
use App\Services\MessageSummary;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcastNow;
use Illuminate\Contracts\Broadcasting\ShouldRescue;
use Illuminate\Foundation\Events\Dispatchable;

/**
 * A message that changed after it was sent (deleted, or its reactions
 * changed), as it is now, for everyone who has the conversation open.
 *
 * Rescued: an unreachable WebSocket server is logged instead of failing
 * the request that changed the message.
 */
class MessageUpdated implements ShouldBroadcastNow, ShouldRescue
{
    use Dispatchable, InteractsWithSockets;

    public function __construct(
        public Message $message,
    ) {}

    /**
     * @return array<int, PrivateChannel>
     */
    public function broadcastOn(): array
    {
        return [new PrivateChannel("conversations.{$this->message->conversation_id}")];
    }

    public function broadcastAs(): string
    {
        return 'message.updated';
    }

    /**
     * @return array{message: array<string, mixed>}
     */
    public function broadcastWith(): array
    {
        return ['message' => MessageSummary::broadcast($this->message)];
    }
}

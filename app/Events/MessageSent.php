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
 * A new message, for everyone who has the conversation open. The payload
 * is the same for every participant (see MessageSummary::broadcast); each
 * client works out which parts are its own.
 *
 * Rescued: an unreachable WebSocket server is logged instead of failing
 * the send. The message is saved either way, and open threads catch up
 * when they reconnect.
 */
class MessageSent implements ShouldBroadcastNow, ShouldRescue
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
        return 'message.sent';
    }

    /**
     * @return array{message: array<string, mixed>}
     */
    public function broadcastWith(): array
    {
        return ['message' => MessageSummary::broadcast($this->message)];
    }
}

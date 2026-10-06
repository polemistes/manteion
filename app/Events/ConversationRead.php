<?php

namespace App\Events;

use App\Models\ConversationParticipant;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcastNow;
use Illuminate\Contracts\Broadcasting\ShouldRescue;
use Illuminate\Foundation\Events\Dispatchable;

/**
 * Someone read a conversation up to now: the others' open threads show
 * "Seen" (or the reader's face) under their last message. Goes to the other
 * participants' own channels.
 */
class ConversationRead implements ShouldBroadcastNow, ShouldRescue
{
    use Dispatchable, InteractsWithSockets;

    public function __construct(
        public ConversationParticipant $participant,
    ) {}

    /**
     * @return list<PrivateChannel>
     */
    public function broadcastOn(): array
    {
        return ConversationParticipant::readReceiptChannelsFor($this->participant->conversation_id, $this->participant->user_id);
    }

    public function broadcastAs(): string
    {
        return 'conversation.read';
    }

    /**
     * @return array{conversation_id: int, user_id: int, read_at: string|null}
     */
    public function broadcastWith(): array
    {
        return [
            'conversation_id' => $this->participant->conversation_id,
            'user_id' => $this->participant->user_id,
            'read_at' => $this->participant->last_read_at?->toIso8601String(),
        ];
    }
}

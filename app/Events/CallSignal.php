<?php

namespace App\Events;

use App\Enums\CallSignalType;
use App\Models\Conversation;
use App\Models\ConversationParticipant;
use App\Models\User;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcastNow;
use Illuminate\Contracts\Broadcasting\ShouldRescue;
use Illuminate\Foundation\Events\Dispatchable;

/**
 * One step of a video call's set-up, passed from one participant's browser
 * to the other's over their own channels. It goes to every participant,
 * the sender included, so the sender's other tabs learn that a call was
 * answered or declined elsewhere; each tab ignores what it sent itself.
 * Nothing is stored: a signal that finds no one is simply gone.
 *
 * Rescued: an unreachable WebSocket server is logged instead of failing
 * the request; the caller's ring then runs out as "no answer".
 */
class CallSignal implements ShouldBroadcastNow, ShouldRescue
{
    use Dispatchable, InteractsWithSockets;

    /**
     * @param  array<string, mixed>  $data
     */
    public function __construct(
        public Conversation $conversation,
        public User $sender,
        public CallSignalType $type,
        public string $callId,
        public string $fromTab,
        public ?string $toTab,
        public array $data,
    ) {}

    /**
     * @return array<int, PrivateChannel>
     */
    public function broadcastOn(): array
    {
        return $this->conversation->participants
            ->map(fn (ConversationParticipant $participant) => new PrivateChannel("App.Models.User.{$participant->user_id}"))
            ->values()
            ->all();
    }

    public function broadcastAs(): string
    {
        return 'call.signal';
    }

    /**
     * @return array{conversation_id: int, call_id: string, type: string, from: array{id: int, name: string, avatar_url: string|null}, from_tab: string, to_tab: string|null, data: array<string, mixed>}
     */
    public function broadcastWith(): array
    {
        return [
            'conversation_id' => $this->conversation->id,
            'call_id' => $this->callId,
            'type' => $this->type->value,
            'from' => [
                'id' => $this->sender->id,
                'name' => $this->sender->name,
                'avatar_url' => $this->sender->avatar_url,
            ],
            'from_tab' => $this->fromTab,
            'to_tab' => $this->toTab,
            'data' => $this->data,
        ];
    }
}

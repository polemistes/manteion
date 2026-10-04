<?php

namespace App\Events;

use App\Models\User;
use App\Services\BadgeCounts;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcastNow;
use Illuminate\Contracts\Broadcasting\ShouldRescue;
use Illuminate\Foundation\Events\Dispatchable;

/**
 * Tells every open tab and device of a user that one of their badge counts
 * may have changed. The counts are computed when the broadcast is sent, so
 * whatever triggered it, clients receive the current numbers.
 *
 * Rescued: an unreachable WebSocket server is logged instead of failing
 * the request that changed a count; clients catch up through GET /badges.
 */
class BadgesUpdated implements ShouldBroadcastNow, ShouldRescue
{
    use Dispatchable, InteractsWithSockets;

    public function __construct(
        public int $userId,
    ) {}

    /**
     * @return array<int, PrivateChannel>
     */
    public function broadcastOn(): array
    {
        return [new PrivateChannel("App.Models.User.{$this->userId}")];
    }

    public function broadcastAs(): string
    {
        return 'badges.updated';
    }

    /**
     * @return array{messages: int, activity: int, requests: int}
     */
    public function broadcastWith(): array
    {
        $user = User::query()->find($this->userId);

        return $user === null ? BadgeCounts::none() : BadgeCounts::for($user);
    }
}

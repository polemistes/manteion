<?php

namespace App\Notifications;

use App\Enums\NotificationCategory;
use App\Models\Friendship;
use App\Models\User;

/**
 * Someone sent the recipient a friend request. Withdrawn when the request
 * is cancelled (see FriendshipController).
 */
class FriendRequestReceived extends ActivityNotification
{
    public function __construct(
        public Friendship $friendship,
    ) {}

    public static function category(): NotificationCategory
    {
        return NotificationCategory::FriendRequests;
    }

    public function actor(): User
    {
        return $this->friendship->requester;
    }

    public function subject(): User
    {
        return $this->friendship->requester;
    }

    /**
     * @return array<string, int>
     */
    protected function references(): array
    {
        return ['friendship_id' => $this->friendship->id];
    }

    protected function pushTitle(): string
    {
        return __('notifications.push.friend_request_received.title');
    }

    protected function pushBody(): string
    {
        return __('notifications.push.friend_request_received.body', ['name' => $this->friendship->requester->name]);
    }
}

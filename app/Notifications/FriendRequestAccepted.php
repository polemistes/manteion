<?php

namespace App\Notifications;

use App\Enums\NotificationCategory;
use App\Models\Friendship;
use App\Models\User;

/**
 * The person the recipient asked to be friends accepted. (Declining is
 * never announced.)
 */
class FriendRequestAccepted extends ActivityNotification
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
        return $this->friendship->addressee;
    }

    public function subject(): User
    {
        return $this->friendship->addressee;
    }

    protected function pushTitle(): string
    {
        return __('notifications.push.friend_request_accepted.title');
    }

    protected function pushBody(): string
    {
        return __('notifications.push.friend_request_accepted.body', ['name' => $this->friendship->addressee->name]);
    }
}

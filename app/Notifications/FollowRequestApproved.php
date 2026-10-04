<?php

namespace App\Notifications;

use App\Enums\NotificationCategory;
use App\Models\Follow;
use App\Models\User;

/**
 * The person the recipient asked to follow approved the request.
 */
class FollowRequestApproved extends ActivityNotification
{
    public function __construct(
        public Follow $follow,
    ) {}

    public static function category(): NotificationCategory
    {
        return NotificationCategory::Followers;
    }

    public function actor(): User
    {
        return $this->follow->followed;
    }

    public function subject(): User
    {
        return $this->follow->followed;
    }

    protected function pushTitle(): string
    {
        return __('notifications.push.follow_request_approved.title');
    }

    protected function pushBody(): string
    {
        return __('notifications.push.follow_request_approved.body', ['name' => $this->follow->followed->name]);
    }
}

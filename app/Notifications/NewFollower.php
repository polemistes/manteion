<?php

namespace App\Notifications;

use App\Enums\FollowStatus;
use App\Enums\NotificationCategory;
use App\Models\Follow;
use App\Models\User;

/**
 * Someone started following the recipient, or asked to (`pending`) when
 * the recipient approves their followers. A request that is withdrawn or
 * turned down takes its notification with it (see FollowController).
 */
class NewFollower extends ActivityNotification
{
    /**
     * Whether this was a request rather than a follow, as it was when sent:
     * by the time the queue gets to it, it may already have been approved.
     */
    public bool $isRequest;

    public function __construct(
        public Follow $follow,
    ) {
        $this->isRequest = $follow->status === FollowStatus::Pending;
    }

    public static function category(): NotificationCategory
    {
        return NotificationCategory::Followers;
    }

    public function actor(): User
    {
        return $this->follow->follower;
    }

    public function subject(): User
    {
        return $this->follow->follower;
    }

    public function pending(): bool
    {
        return $this->isRequest;
    }

    /**
     * @return array<string, int>
     */
    protected function references(): array
    {
        return ['follow_id' => $this->follow->id];
    }

    protected function pushTitle(): string
    {
        return $this->isRequest
            ? __('notifications.push.follow_request.title')
            : __('notifications.push.new_follower.title');
    }

    protected function pushBody(): string
    {
        return $this->isRequest
            ? __('notifications.push.follow_request.body', ['name' => $this->follow->follower->name])
            : __('notifications.push.new_follower.body', ['name' => $this->follow->follower->name]);
    }
}

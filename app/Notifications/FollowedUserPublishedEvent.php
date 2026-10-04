<?php

namespace App\Notifications;

use App\Enums\NotificationCategory;
use App\Models\Event;
use App\Models\User;

/**
 * Someone the recipient follows published an event (see
 * App\Jobs\NotifyFollowersOfEvent).
 */
class FollowedUserPublishedEvent extends ActivityNotification
{
    public function __construct(
        public Event $event,
    ) {}

    public static function category(): NotificationCategory
    {
        return NotificationCategory::FollowedEvents;
    }

    public function actor(): User
    {
        return $this->event->owner;
    }

    public function subject(): Event
    {
        return $this->event;
    }

    protected function pushTitle(): string
    {
        return __('notifications.push.followed_user_published_event.title');
    }

    protected function pushBody(): string
    {
        return __('notifications.push.followed_user_published_event.body', [
            'name' => $this->event->owner->name,
            'event' => $this->event->name,
        ]);
    }
}

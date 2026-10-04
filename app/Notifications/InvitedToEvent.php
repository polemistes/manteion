<?php

namespace App\Notifications;

use App\Enums\NotificationCategory;
use App\Models\Event;
use App\Models\User;

/**
 * Someone invited the recipient to an event.
 */
class InvitedToEvent extends ActivityNotification
{
    public function __construct(
        public Event $event,
        public User $invitedBy,
    ) {}

    public static function category(): NotificationCategory
    {
        return NotificationCategory::EventInvites;
    }

    public function actor(): User
    {
        return $this->invitedBy;
    }

    public function subject(): Event
    {
        return $this->event;
    }

    protected function pushTitle(): string
    {
        return __('notifications.push.invited_to_event.title');
    }

    protected function pushBody(): string
    {
        return __('notifications.push.invited_to_event.body', [
            'name' => $this->invitedBy->name,
            'event' => $this->event->name,
        ]);
    }
}

<?php

namespace App\Notifications;

use App\Enums\NotificationCategory;
use App\Models\Organizer;
use App\Models\OrganizerSpokesperson;
use App\Models\User;

/**
 * "Robert invited you to speak for Kafé Hærverk": answered in Activity.
 */
class SpokespersonInvited extends ActivityNotification
{
    public function __construct(
        public OrganizerSpokesperson $membership,
        public User $inviter,
    ) {}

    public static function category(): NotificationCategory
    {
        return NotificationCategory::OrganizerInvites;
    }

    public function actor(): User
    {
        return $this->inviter;
    }

    public function subject(): Organizer
    {
        return $this->membership->organizer;
    }

    public function pending(): ?bool
    {
        return $this->membership->fresh()?->isPending() ?? false;
    }

    /**
     * @return array<string, int>
     */
    protected function references(): array
    {
        return ['spokesperson_id' => $this->membership->id];
    }

    protected function pushTitle(): string
    {
        return __('notifications.push.spokesperson_invited.title');
    }

    protected function pushBody(): string
    {
        return __('notifications.push.spokesperson_invited.body', [
            'name' => $this->inviter->name,
            'organizer' => $this->membership->organizer->name,
        ]);
    }
}

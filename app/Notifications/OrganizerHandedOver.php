<?php

namespace App\Notifications;

use App\Enums\NotificationCategory;
use App\Models\Organizer;
use App\Models\User;

/**
 * "Robert stepped down from Kafé Hærverk": to the spokesperson who was
 * handed what the leaver made for the organizer (so many events, leaflets
 * and templates, now in their name), or the main role, or both. When the
 * leaver's account is being deleted there is no one to name (`$leaver` is
 * null): nothing of a deleted account stays in other people's Activity,
 * and a queued send that held the person would be dropped with them.
 */
class OrganizerHandedOver extends ActivityNotification
{
    /**
     * @param  array{events: int, leaflets: int, templates: int}  $handed
     */
    public function __construct(
        public Organizer $organizer,
        public ?User $leaver,
        public bool $madeMain,
        public array $handed,
    ) {}

    public static function category(): NotificationCategory
    {
        return NotificationCategory::OrganizerInvites;
    }

    public function actor(): ?User
    {
        return $this->leaver;
    }

    public function subject(): Organizer
    {
        return $this->organizer;
    }

    /**
     * Not ids this once, but what was handed over, for the Activity row
     * to spell out (ActivityFeed::item() passes it on as `handover`).
     *
     * @return array<string, int>
     */
    protected function references(): array
    {
        return [
            'made_main' => (int) $this->madeMain,
            'handed_events' => $this->handed['events'],
            'handed_leaflets' => $this->handed['leaflets'],
            'handed_templates' => $this->handed['templates'],
        ];
    }

    protected function pushTitle(): string
    {
        return __('notifications.push.organizer_handed_over.title', ['organizer' => $this->organizer->name]);
    }

    protected function pushBody(): string
    {
        if ($this->madeMain) {
            return __('notifications.push.organizer_handed_over.made_main', ['organizer' => $this->organizer->name]);
        }

        return $this->leaver === null
            ? __('notifications.push.organizer_handed_over.body_unnamed', ['organizer' => $this->organizer->name])
            : __('notifications.push.organizer_handed_over.body', ['name' => $this->leaver->name, 'organizer' => $this->organizer->name]);
    }
}

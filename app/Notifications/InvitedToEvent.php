<?php

namespace App\Notifications;

use App\Models\Event;
use App\Models\User;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Notification;

class InvitedToEvent extends Notification
{
    use Queueable;

    public function __construct(
        public Event $event,
        public User $invitedBy,
    ) {}

    /**
     * @return array<int, string>
     */
    public function via(object $notifiable): array
    {
        return ['database'];
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(object $notifiable): array
    {
        return [
            'event_id' => $this->event->id,
            'event_name' => $this->event->name,
            'invited_by_user_id' => $this->invitedBy->id,
            'invited_by_name' => $this->invitedBy->name,
        ];
    }
}

<?php

namespace App\Notifications;

use App\Models\Event;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Notification;
use Illuminate\Queue\SerializesModels;

class FollowedUserPublishedEvent extends Notification implements ShouldQueue
{
    use Queueable, SerializesModels;

    public function __construct(
        public Event $event,
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
            'owner_id' => $this->event->user_id,
            'owner_name' => $this->event->owner->name,
        ];
    }
}

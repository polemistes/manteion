<?php

namespace App\Notifications;

use App\Models\Event;
use App\Models\User;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Notification;
use NotificationChannels\WebPush\WebPushChannel;
use NotificationChannels\WebPush\WebPushMessage;

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
    public function via(User $notifiable): array
    {
        $channels = ['database'];

        if ($notifiable->push_notifications_enabled && $notifiable->notify_event_invites) {
            $channels[] = WebPushChannel::class;
        }

        return $channels;
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

    public function toWebPush(object $notifiable, self $notification): WebPushMessage
    {
        return (new WebPushMessage)
            ->title(__('messages.push_notification.event_invite_title'))
            ->body(__('messages.push_notification.event_invite_body', [
                'name' => $this->invitedBy->name,
                'event' => $this->event->name,
            ]))
            ->data(['url' => "/events/{$this->event->id}"]);
    }
}

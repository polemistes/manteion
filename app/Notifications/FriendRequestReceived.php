<?php

namespace App\Notifications;

use App\Models\Friendship;
use App\Models\User;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Notification;
use NotificationChannels\WebPush\WebPushChannel;
use NotificationChannels\WebPush\WebPushMessage;

class FriendRequestReceived extends Notification
{
    use Queueable;

    public function __construct(
        public Friendship $friendship,
    ) {}

    /**
     * @return array<int, string>
     */
    public function via(User $notifiable): array
    {
        if ($notifiable->push_notifications_enabled && $notifiable->notify_friend_requests) {
            return [WebPushChannel::class];
        }

        return [];
    }

    public function toWebPush(object $notifiable, self $notification): WebPushMessage
    {
        return (new WebPushMessage)
            ->title(__('messages.push_notification.friend_request_title'))
            ->body(__('messages.push_notification.friend_request_body', ['name' => $this->friendship->requester->name]))
            ->data(['url' => "/users/{$this->friendship->requester_id}"]);
    }
}

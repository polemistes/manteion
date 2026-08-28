<?php

namespace App\Notifications;

use App\Enums\FollowStatus;
use App\Models\Follow;
use App\Models\User;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Notification;
use NotificationChannels\WebPush\WebPushChannel;
use NotificationChannels\WebPush\WebPushMessage;

class NewFollower extends Notification
{
    use Queueable;

    public function __construct(
        public Follow $follow,
    ) {}

    /**
     * @return array<int, string>
     */
    public function via(User $notifiable): array
    {
        if ($notifiable->push_notifications_enabled && $notifiable->notify_follow_requests) {
            return [WebPushChannel::class];
        }

        return [];
    }

    public function toWebPush(object $notifiable, self $notification): WebPushMessage
    {
        $pending = $this->follow->status === FollowStatus::Pending;

        return (new WebPushMessage)
            ->title(__($pending
                ? 'messages.push_notification.follow_request_title'
                : 'messages.push_notification.new_follower_title'))
            ->body(__($pending
                ? 'messages.push_notification.follow_request_body'
                : 'messages.push_notification.new_follower_body', ['name' => $this->follow->follower->name]))
            ->data(['url' => "/users/{$this->follow->follower_id}"]);
    }
}

<?php

namespace App\Notifications;

use App\Models\Message;
use App\Models\User;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Notification;
use Illuminate\Queue\SerializesModels;
use NotificationChannels\WebPush\WebPushChannel;
use NotificationChannels\WebPush\WebPushMessage;

class NewMessageReceived extends Notification implements ShouldQueue
{
    use Queueable, SerializesModels;

    public function __construct(
        public Message $message,
    ) {}

    /**
     * @return array<int, string>
     */
    public function via(User $notifiable): array
    {
        if ($notifiable->push_notifications_enabled && $notifiable->notify_messages) {
            return [WebPushChannel::class];
        }

        return [];
    }

    public function toWebPush(object $notifiable, self $notification): WebPushMessage
    {
        return (new WebPushMessage)
            ->title(__('messages.push_notification.new_message_title'))
            ->body(__('messages.push_notification.new_message_body', ['name' => $this->message->sender->name]))
            ->data(['url' => "/messages/{$this->message->conversation_id}"]);
    }
}

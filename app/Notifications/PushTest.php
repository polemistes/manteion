<?php

namespace App\Notifications;

use Illuminate\Notifications\Notification;
use NotificationChannels\WebPush\WebPushChannel;
use NotificationChannels\WebPush\WebPushMessage;

/**
 * "Send a test" in the notification settings: a push to the one device that
 * asked (see User::routeNotificationForWebPush), sent at once, so the user
 * sees that notifications get through to it.
 */
class PushTest extends Notification
{
    public function __construct(public readonly string $endpoint) {}

    /**
     * @return array<int, string>
     */
    public function via(object $notifiable): array
    {
        return [WebPushChannel::class];
    }

    public function toWebPush(object $notifiable, self $notification): WebPushMessage
    {
        return (new WebPushMessage)
            ->title(__('notifications.push.test.title'))
            ->body(__('notifications.push.test.body'))
            ->icon('/icons/icon-192.png')
            ->badge('/icons/badge-96.png')
            ->tag('push-test')
            ->renotify()
            ->lang(app()->getLocale())
            ->data([
                'url' => route('settings.notifications', absolute: false),
                'type' => 'test',
            ])
            ->options(['TTL' => 300, 'urgency' => 'high']);
    }
}

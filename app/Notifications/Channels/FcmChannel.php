<?php

namespace App\Notifications\Channels;

use App\Enums\FcmDelivery;
use App\Models\PushSubscription;
use App\Notifications\Messages\FcmMessage;
use App\Services\Fcm;
use Illuminate\Notifications\Notification;
use Illuminate\Support\Facades\Log;
use NotificationChannels\WebPush\WebPushMessage;

/**
 * Push to the user's Android app installs through Firebase Cloud Messaging,
 * beside WebPushChannel for their browsers. A notification that lists
 * WebPushChannel lists this one too; each goes only to its own kind of
 * device (User::routeNotificationForFcm), and the message is the
 * notification's own toWebPush(). An install whose token FCM says is dead
 * is removed from the user's devices.
 */
class FcmChannel
{
    private static bool $reportedUnconfigured = false;

    public function __construct(private Fcm $fcm) {}

    public function send(mixed $notifiable, Notification $notification): void
    {
        if (! is_object($notifiable) || ! method_exists($notifiable, 'routeNotificationFor') || ! method_exists($notification, 'toWebPush')) {
            return;
        }

        $devices = $notifiable->routeNotificationFor('fcm', $notification);

        if ($devices === null || $devices->isEmpty()) {
            return;
        }

        if (! Fcm::configured()) {
            if (! self::$reportedUnconfigured) {
                self::$reportedUnconfigured = true;
                Log::debug('FCM is not configured (FCM_CREDENTIALS); app devices get no pushes.');
            }

            return;
        }

        $webPush = $notification->toWebPush($notifiable, $notification);

        if (! $webPush instanceof WebPushMessage) {
            return;
        }

        $message = FcmMessage::fromWebPush($webPush)->toArray();

        /** @var PushSubscription $device */
        foreach ($devices as $device) {
            if ($this->fcm->send($device->endpoint, $message) === FcmDelivery::Unregistered) {
                $device->delete();
            }
        }
    }
}

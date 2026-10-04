<?php

namespace App\Notifications\Messages;

use NotificationChannels\WebPush\WebPushMessage;

/**
 * The package's web push message plus the Notifications API's `timestamp`
 * (when the thing happened, in milliseconds), which it lacks. The service
 * worker passes it to showNotification(), so a push delivered late (from an
 * offline phone) still shows the time the message was written. toArray()
 * picks the property up like the others.
 */
class TimestampedWebPushMessage extends WebPushMessage
{
    protected ?int $timestamp = null;

    public function timestamp(int $milliseconds): static
    {
        $this->timestamp = $milliseconds;

        return $this;
    }
}

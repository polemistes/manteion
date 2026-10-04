<?php

namespace App\Models;

use Database\Factories\PushSubscriptionFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Support\Carbon;
use NotificationChannels\WebPush\PushSubscription as WebPushSubscription;

/**
 * One device (one browser) that gets its owner's push notifications: the
 * webpush package's subscription plus the browser's user agent, which names
 * the device in the notification settings. Registered as the package's
 * model in config/webpush.php.
 *
 * @property int $id
 * @property string|null $user_agent
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
#[Fillable(['user_agent'])]
class PushSubscription extends WebPushSubscription
{
    /** @use HasFactory<PushSubscriptionFactory> */
    use HasFactory;
}

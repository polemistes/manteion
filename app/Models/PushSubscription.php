<?php

namespace App\Models;

use App\Enums\PushKind;
use Database\Factories\PushSubscriptionFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Support\Carbon;
use NotificationChannels\WebPush\PushSubscription as WebPushSubscription;

/**
 * One device that gets its owner's push notifications: a browser (the
 * webpush package's subscription) or the Android app (its FCM registration
 * token in `endpoint`, no keys), plus the user agent, which names the device
 * in the notification settings. Registered as the package's model in
 * config/webpush.php.
 *
 * @property int $id
 * @property PushKind $kind
 * @property string|null $user_agent
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
#[Fillable(['user_agent', 'kind'])]
class PushSubscription extends WebPushSubscription
{
    /** @use HasFactory<PushSubscriptionFactory> */
    use HasFactory;

    /**
     * Every row is a browser unless it says otherwise.
     *
     * @var array<string, mixed>
     */
    protected $attributes = [
        'kind' => 'webpush',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'kind' => PushKind::class,
        ];
    }

    /**
     * Browsers, which get pushes through WebPushChannel.
     *
     * @param  Builder<self>  $query
     * @return Builder<self>
     */
    protected function scopeWebPush(Builder $query): Builder
    {
        return $query->where('kind', PushKind::WebPush);
    }

    /**
     * Android app installs, which get pushes through FcmChannel.
     *
     * @param  Builder<self>  $query
     * @return Builder<self>
     */
    protected function scopeFcm(Builder $query): Builder
    {
        return $query->where('kind', PushKind::Fcm);
    }
}

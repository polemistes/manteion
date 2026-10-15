<?php

namespace App\Enums;

/**
 * How a device gets its pushes: through its browser's push service (web
 * push, with VAPID) or through the Android app (Firebase Cloud Messaging,
 * where the subscription's endpoint is the app's registration token).
 */
enum PushKind: string
{
    case WebPush = 'webpush';
    case Fcm = 'fcm';
}

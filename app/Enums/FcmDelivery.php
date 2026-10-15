<?php

namespace App\Enums;

/**
 * What became of one push sent through Firebase Cloud Messaging.
 */
enum FcmDelivery
{
    /** FCM accepted it for the device. */
    case Sent;

    /** The token no longer reaches an app install (uninstalled, signed out, another project's). */
    case Unregistered;

    /** Anything else; logged, and the device is kept. */
    case Failed;
}

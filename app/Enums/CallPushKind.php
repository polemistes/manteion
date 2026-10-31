<?php

namespace App\Enums;

/**
 * The pushes a video call sends to the callee's devices, by the `type` the
 * service worker and the Android app read.
 */
enum CallPushKind: string
{
    /** Someone is ringing: shows until taken, refused, given up or run out. */
    case Ring = 'call';

    /** The caller gave up before anyone answered; replaces the ring. */
    case Missed = 'missed_call';

    /** The ring was taken or refused on some device: the others take it down. */
    case Over = 'call_over';
}

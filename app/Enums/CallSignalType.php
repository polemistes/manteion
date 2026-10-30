<?php

namespace App\Enums;

/**
 * The messages two browsers exchange through the server to set up, run and
 * end a call. Everything but the ring, the cancel and the busy reply is
 * addressed to one tab of the other person (see CallSignal::$toTab).
 */
enum CallSignalType: string
{
    /** The caller asks; media is already in hand on their side. */
    case Ring = 'ring';

    /** The caller gave up or hung up before anyone answered. */
    case Cancel = 'cancel';

    /** One of the callee's tabs took the call; the caller sends the offer there. */
    case Accept = 'accept';

    /** The callee said no (or their camera failed: data.reason = "media"). */
    case Decline = 'decline';

    /** The callee is already in a call. */
    case Busy = 'busy';

    /** The caller's session description (data.sdp). */
    case Offer = 'offer';

    /** The callee's session description (data.sdp). */
    case Answer = 'answer';

    /** A batch of ICE candidates (data.candidates), either way. */
    case Candidates = 'candidates';

    /**
     * One side's camera or microphone state (data.camera, data.mic,
     * data.hasCamera): sent once connected and whenever it changes, since
     * a stopped video track does not reliably mute at the other end.
     */
    case Media = 'media';

    /** Either side ended a call that was accepted. */
    case Hangup = 'hangup';
}

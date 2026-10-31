/** One step of a call's set-up (App\Enums\CallSignalType). */
export type CallSignalType =
    | 'ring'
    | 'cancel'
    | 'accept'
    | 'decline'
    | 'busy'
    | 'offer'
    | 'answer'
    | 'candidates'
    | 'media'
    | 'hangup';

/** The other person on a call, as the ring and the call window show them. */
export type CallPeer = {
    id: number;
    name: string;
    avatar_url: string | null;
};

/**
 * `.call.signal` on a user's own channel: one step of a call's set-up,
 * from either participant. The sender's other tabs get it too, so they can
 * drop a ring that was answered elsewhere; a tab ignores what it sent.
 */
export type CallSignal = {
    conversation_id: number;
    call_id: string;
    type: CallSignalType;
    from: CallPeer;
    /** The sending tab. */
    from_tab: string;
    /** The one tab this is for once the call is answered; null for everyone. */
    to_tab: string | null;
    /** sdp, candidates, reason…; an empty one arrives as `[]`. */
    data: Record<string, unknown>;
};

/** A ring the server still holds for the user (GET calls.ringing), for a page that opened after the broadcast. */
export type PendingRing = {
    conversation_id: number;
    call_id: string;
    from: CallPeer;
    from_tab: string;
    /** Server clock, milliseconds. */
    started_at: number;
    expires_at: number;
};

/** The shared `calls` page prop: on or off, and the STUN servers (never a relay). */
export type CallsConfig = {
    enabled: boolean;
    iceServers: RTCIceServer[];
};

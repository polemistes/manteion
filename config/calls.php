<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Video calls
    |--------------------------------------------------------------------------
    |
    | Calls run peer to peer (WebRTC) between the two browsers; the server
    | only passes the small set-up messages over Reverb. There is no relay
    | (TURN) on purpose: the server's line is metered, so when two networks
    | won't connect directly the call fails and the page says so.
    |
    */

    'enabled' => (bool) env('CALLS_ENABLED', true),

    /*
    | STUN servers tell a browser its public address so the two sides can
    | find each other. They carry no media and next to no traffic. The
    | browser contacts them directly, so the default (Google's) sees the
    | user's IP address; set CALLS_STUN_SERVERS to your own, comma-separated,
    | to avoid that. Only stun:/stuns: entries belong here — never a relay.
    */

    'stun_servers' => array_values(array_filter(array_map(
        'trim',
        explode(',', (string) env('CALLS_STUN_SERVERS', 'stun:stun.l.google.com:19302')),
    ))),

];

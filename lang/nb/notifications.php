<?php

return [

    // Push notifications for activity (App\Notifications\ActivityNotification
    // and its kinds). The Activity page words these items on the client.
    'push' => [
        'test' => [
            'title' => 'Manteion',
            'body' => 'Varsler fungerer på denne enheten.',
        ],
        'invited_to_event' => [
            'title' => 'Arrangementsinvitasjon',
            'body' => ':name inviterte deg til «:event»',
        ],
        'followed_user_published_event' => [
            'title' => 'Nytt arrangement',
            'body' => ':name publiserte «:event»',
        ],
        'new_event_comment' => [
            'title' => 'Ny kommentar på «:event»',
            'body' => ':name: :excerpt',
        ],
        'friend_request_received' => [
            'title' => 'Venneforespørsel',
            'body' => ':name sendte deg en venneforespørsel',
        ],
        'friend_request_accepted' => [
            'title' => 'Venneforespørsel godtatt',
            'body' => ':name godtok venneforespørselen din',
        ],
        'new_follower' => [
            'title' => 'Ny følger',
            'body' => ':name begynte å følge deg',
        ],
        'follow_request' => [
            'title' => 'Følgeforespørsel',
            'body' => ':name vil følge deg',
        ],
        'follow_request_approved' => [
            'title' => 'Følgeforespørsel godkjent',
            'body' => ':name godkjente følgeforespørselen din',
        ],
        'new_leaflet_response' => [
            'title' => 'Nytt svar på flyvebladet ditt',
            'body' => ':name: :excerpt',
        ],
    ],

];

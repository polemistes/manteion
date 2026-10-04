<?php

return [

    // Push notifications for activity (App\Notifications\ActivityNotification
    // and its kinds). The Activity page words these items on the client.
    'push' => [
        'test' => [
            'title' => 'Manteion',
            'body' => 'Notifications work on this device.',
        ],
        'invited_to_event' => [
            'title' => 'Event invitation',
            'body' => ':name invited you to “:event”',
        ],
        'followed_user_published_event' => [
            'title' => 'New event',
            'body' => ':name published “:event”',
        ],
        'new_event_comment' => [
            'title' => 'New comment on “:event”',
            'body' => ':name: :excerpt',
        ],
        'friend_request_received' => [
            'title' => 'Friend request',
            'body' => ':name sent you a friend request',
        ],
        'friend_request_accepted' => [
            'title' => 'Friend request accepted',
            'body' => ':name accepted your friend request',
        ],
        'new_follower' => [
            'title' => 'New follower',
            'body' => ':name started following you',
        ],
        'follow_request' => [
            'title' => 'Follow request',
            'body' => ':name wants to follow you',
        ],
        'follow_request_approved' => [
            'title' => 'Follow request approved',
            'body' => ':name approved your follow request',
        ],
        'new_leaflet_response' => [
            'title' => 'New reply to your leaflet',
            'body' => ':name: :excerpt',
        ],
    ],

];

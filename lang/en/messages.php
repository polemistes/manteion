<?php

return [

    'friendship' => [
        'cannot_request_self' => 'You cannot send a friend request to yourself.',
        'already_related' => 'A friend request already exists with this user.',
    ],

    'follow' => [
        'cannot_follow_self' => 'You cannot follow yourself.',
        'already_following' => 'You are already following this user, or have a pending request.',
    ],

    'event_invitation' => [
        'friends_only' => 'You can only invite your friends.',
    ],

    'contact_group_member' => [
        'not_a_friend' => 'This user is not one of your friends.',
        'not_followed' => 'You are not following this user.',
    ],

    'conversation' => [
        'user_missing' => 'One of the selected people no longer exists.',
        'friends_only' => 'You can only message your friends.',
    ],

    'conversation_participant' => [
        'friends_only' => 'You can only add your own friends.',
    ],

    'organizer' => [
        'friends_only' => 'You can only invite your friends to speak for an organizer.',
        'already_in' => 'They already speak for this organizer, or have been invited.',
        'hand_over_first' => 'Hand the main spokesperson role to someone else first, or delete the organizer.',
        'hand_over_to_member' => 'Only a spokesperson who has accepted can take over.',
    ],

    'leaflet' => [
        'one_attachment' => 'A leaflet can carry either an image or a link, not both.',
    ],

    'reaction' => [
        'not_an_emoji' => 'That is not a single emoji.',
    ],

    'moderation' => [
        'deleted_placeholder' => 'Deleted by a moderator',
    ],

    'password_reset' => [
        'subject' => 'Reset your password',
        'intro' => 'We received a request to reset the password for the account using this email address.',
        'multiple_intro' => 'We received a request to reset the password for the accounts using this email address. Choose the one you want to reset:',
        'account_line' => 'Account: :username',
        'button' => 'Reset password',
        'ignore' => "If you didn't request this, you can safely ignore this email.",
    ],

];

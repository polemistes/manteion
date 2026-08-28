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

    'push_notification' => [
        'event_invite_title' => 'Event invitation',
        'event_invite_body' => ':name invited you to ":event"',
        'new_message_title' => 'New message',
        'new_message_body' => ':name sent you a message',
        'friend_request_title' => 'Friend request',
        'friend_request_body' => ':name sent you a friend request',
        'new_follower_title' => 'New follower',
        'new_follower_body' => ':name started following you',
        'follow_request_title' => 'Follow request',
        'follow_request_body' => ':name wants to follow you',
    ],

];

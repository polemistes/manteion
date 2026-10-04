<?php

use App\Models\Conversation;
use App\Models\Event;
use App\Models\User;
use Illuminate\Support\Facades\Broadcast;

// Badges, incoming-message toasts and notification broadcasts: only the
// user themselves.
Broadcast::channel('App.Models.User.{id}', function (User $user, string $id) {
    return $user->id === (int) $id;
});

// New, changed and deleted messages, participant changes and typing
// whispers: participants only. Staff can read a reported conversation, but
// they don't get its live feed.
Broadcast::channel('conversations.{conversation}', function (User $user, Conversation $conversation) {
    return $conversation->isVisibleTo($user);
});

// Comments, attendance and changes on an event page: whoever may view it.
Broadcast::channel('events.{event}', function (User $user, Event $event) {
    return $user->can('view', $event);
});

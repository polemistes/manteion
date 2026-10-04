<?php

namespace App\Enums;

/**
 * What a notification is about, for the per-category push settings. Cases
 * are in display order: messages first, then activity.
 */
enum NotificationCategory: string
{
    case DirectMessages = 'direct_messages';
    case GroupMessages = 'group_messages';
    case EventInvites = 'event_invites';
    case EventComments = 'event_comments';
    case FollowedEvents = 'followed_events';
    case FriendRequests = 'friend_requests';
    case Followers = 'followers';
    case LeafletResponses = 'leaflet_responses';

    /**
     * Whether push is on for this category until the user says otherwise:
     * only messages are, everything else is opt-in.
     */
    public function pushDefault(): bool
    {
        return match ($this) {
            self::DirectMessages, self::GroupMessages => true,
            self::EventInvites, self::EventComments, self::FollowedEvents,
            self::FriendRequests, self::Followers, self::LeafletResponses => false,
        };
    }

    /**
     * @return list<self>
     */
    public static function messageCategories(): array
    {
        return [self::DirectMessages, self::GroupMessages];
    }

    /**
     * @return list<self>
     */
    public static function activityCategories(): array
    {
        return [
            self::EventInvites,
            self::EventComments,
            self::FollowedEvents,
            self::FriendRequests,
            self::Followers,
            self::LeafletResponses,
        ];
    }
}

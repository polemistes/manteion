/** App\Enums\NotificationCategory: what a push setting switches on or off. */
export type NotificationCategory =
    | 'direct_messages'
    | 'group_messages'
    | 'event_invites'
    | 'event_comments'
    | 'followed_events'
    | 'friend_requests'
    | 'followers'
    | 'leaflet_responses';

/** The notification class basename behind an activity item. */
export type ActivityType =
    | 'InvitedToEvent'
    | 'FollowedUserPublishedEvent'
    | 'NewEventComment'
    | 'FriendRequestReceived'
    | 'FriendRequestAccepted'
    | 'NewFollower'
    | 'FollowRequestApproved'
    | 'NewLeafletResponse';

export type ActivityActor = {
    id: number;
    name: string;
    username: string;
    avatar_url: string | null;
};

export type ActivitySubjectType = 'event' | 'leaflet' | 'user';

/**
 * One entry on the Activity page: a database notification, as the page's
 * `notifications` prop lists it and as its broadcast arrives live (see
 * onActivity in @/realtime/activity).
 */
export type ActivityItem = {
    /** The notification's UUID. */
    id: string;
    type: ActivityType;
    category: NotificationCategory;
    /** Null when the person who caused it no longer exists. */
    actor: ActivityActor | null;
    subject_type: ActivitySubjectType;
    subject_id: number;
    subject_title: string;
    /** The comment or response text, for NewEventComment and NewLeafletResponse. */
    excerpt: string | null;
    /** NewFollower only: true for a follow request, false for a follow. */
    pending: boolean | null;
    url: string;
    /** Whether it was unread when the page loaded (or arrived live). */
    unread: boolean;
    created_at: string;
};

export type EventActivityKind =
    'comment' | 'attendance' | 'invitation' | 'updated' | 'deleted';

/**
 * `.event.activity` on the `events.{id}` channel: something on an event page
 * changed, so whoever is looking at it can reload the affected props.
 */
export type EventActivity = {
    event_id: number;
    kind: EventActivityKind;
};

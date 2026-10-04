import { router } from '@inertiajs/vue3';
import { store as startConversation } from '@/routes/messages';

export interface Person {
    id: number;
    name: string;
    username: string;
    avatar_url: string | null;
}

export interface FriendEntry extends Person {
    tag_preference_ids: number[];
    muted: boolean;
    friendship_id: number;
    friends_since: string | null;
    /** Null where their friends list is private. */
    mutual_count: number | null;
}

export interface FriendRequestEntry {
    friendship_id: number;
    created_at: string | null;
    mutual_count?: number | null;
    user: Person;
}

export interface FollowEntry {
    follow_id: number;
    since: string | null;
    user: Person;
    tag_preference_ids?: number[];
    muted?: boolean;
    follows_back?: 'following' | 'pending' | null;
    mutual_count?: number | null;
}

export interface GroupEntry {
    id: number;
    name: string;
    members: Person[];
}

export type GroupType = 'friend' | 'following';

export interface FindResult extends Person {
    friend_status: 'none' | 'request_sent' | 'request_received' | 'friends';
    friendship_id: number | null;
    follow_status: 'none' | 'pending' | 'following';
    follow_id: number | null;
    mutual_count: number | null;
}

/** The props the page reloads after anything changes about a person. */
export const PEOPLE_PROPS = [
    'friends',
    'pendingFriendRequests',
    'sentFriendRequests',
    'following',
    'pendingFollowRequestsSent',
    'followers',
    'pendingFollowerRequests',
    'friendGroups',
    'followingGroups',
    'findResults',
];

/** Opens the direct conversation with someone (creating it the first time). */
export function messagePerson(person: Person): void {
    router.post(startConversation().url, { user_ids: [person.id] });
}

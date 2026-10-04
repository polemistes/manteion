/**
 * The numbers behind the app's badges (App\Services\BadgeCounts). The same
 * shape arrives as the shared `badges` page prop, from GET /badges and in the
 * `.badges.updated` broadcast; read it through useBadges() in
 * @/realtime/badges rather than from the page props, so it stays live.
 */
export type Badges = {
    /** Unmuted conversations with a message from someone else since the user last read them. */
    messages: number;
    /** Unread activity (database notifications). */
    activity: number;
    /** Friend and follow requests from others that still await an answer. */
    requests: number;
};

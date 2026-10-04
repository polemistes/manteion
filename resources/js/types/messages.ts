import type { ReactionSummary } from '@/reactions';

export type { ReactionSummary } from '@/reactions';

export type ConversationType = 'direct' | 'group';

/** The author of a message, as every message shape carries it. */
export type MessageSender = {
    id: number;
    name: string;
    username: string;
    avatar_url: string | null;
};

/** One emoji on a broadcast message, with everyone who reacted with it. */
export type BroadcastReaction = {
    emoji: string;
    count: number;
    user_ids: number[];
};

/**
 * A message as `.message.sent` and `.message.updated` carry it on the
 * `conversations.{id}` channel. It is the same for every participant, so the
 * viewer-specific fields of a MessageSummary are worked out on the client
 * (toMessageSummary in @/realtime/conversation).
 */
export type BroadcastMessage = {
    id: number;
    conversation_id: number;
    /** Null once deleted; a moderator's placeholder text passes through. */
    body: string | null;
    is_deleted: boolean;
    created_at: string;
    sender: MessageSender;
    /** Ordered like Reaction::summarize: most used first, then oldest first. */
    reactions: BroadcastReaction[];
};

/** A message as the conversation page renders it (App\Services\MessageSummary). */
export type MessageSummary = {
    id: number;
    /** Null once deleted; a moderator's placeholder text passes through. */
    body: string | null;
    is_deleted: boolean;
    created_at: string;
    sender: MessageSender;
    is_mine: boolean;
    can_delete: boolean;
    can_report: boolean;
    reactions: ReactionSummary[];
};

/**
 * `.message.received` on the user's own channel: a message in one of their
 * conversations they have not muted, sent by someone else. Drives in-app
 * toasts, the message sound and the hidden tab's title.
 */
export type IncomingMessage = {
    conversation_id: number;
    conversation_type: ConversationType;
    conversation_title: string;
    message_id: number;
    sender: { id: number; name: string; avatar_url: string | null };
    /** The message text, cut to 120 characters. */
    preview: string;
};

/** Someone typing in a conversation, from their `typing` whisper. */
export type TypingUser = {
    id: number;
    name: string;
};

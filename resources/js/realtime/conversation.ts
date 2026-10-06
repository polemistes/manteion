import { usePage } from '@inertiajs/vue3';
import {
    onBeforeUnmount,
    onMounted,
    readonly,
    ref,
    shallowRef,
    toValue,
    watch,
} from 'vue';
import type { DeepReadonly, MaybeRefOrGetter, Ref } from 'vue';
import {
    clearNotifications,
    onPushMessage,
    postFromServiceWorker,
} from '@/pwa/serviceWorker';
import { presence, read } from '@/routes/messages';
import { index as conversationMessages } from '@/routes/messages/messages';
import type {
    BroadcastMessage,
    MessageSummary,
    TypingUser,
    User,
} from '@/types';
import { connectionState, joinPrivateChannel, onSubscribed } from './echo';
import type { PrivateChannel } from './echo';
import { requestJson } from './http';

/** The server forgets a viewer after 45 seconds without a heartbeat. */
const PRESENCE_HEARTBEAT_MS = 25_000;
const TYPING_WHISPER_EVERY_MS = 3_000;
const TYPING_SHOWN_FOR_MS = 5_000;

/** How often an open conversation asks for new messages while the WebSocket is down. */
const FALLBACK_POLL_MS = 15_000;

export type ConversationChannelOptions = {
    /**
     * A new message: broadcast by the server, or caught up after the
     * subscription (re)starts. It can be one the page already has (its own
     * send, a repeat after a reconnect), so merge by id.
     */
    onMessage?: (message: MessageSummary) => void;
    /** A message changed: deleted, or its reactions. Replace it by id. */
    onUpdate?: (message: MessageSummary) => void;
    /** Someone joined or left: reload the participants. */
    onParticipants?: () => void;
    /** Someone else read the conversation up to `readAt`, for "Seen". */
    onRead?: (userId: number, readAt: string | null) => void;
    /** Someone else started (or is still) typing. typingUsers tracks the same. */
    onTyping?: (user: TypingUser) => void;
    /**
     * The newest message id the page holds (null or 0 when it has none).
     * Messages after it are fetched whenever the subscription (re)starts, so
     * nothing sent between the page load and the subscription, or during a
     * dropped connection, goes missing.
     */
    lastMessageId?: () => number | null | undefined;
};

type Viewer = Pick<User, 'id' | 'name' | 'role' | 'share_typing'>;

const viewed = shallowRef<number | null>(null);

/**
 * The conversation on screen right now (its page visible in this tab), or
 * null; e.g. to skip toasts and sounds for messages the user is reading.
 */
export const viewedConversationId: Readonly<Ref<number | null>> =
    readonly(viewed);

/**
 * A broadcast message from the given viewer's side: whether it is theirs,
 * what they may do with it, and which reactions are their own.
 */
export function toMessageSummary(
    message: BroadcastMessage,
    viewer: Viewer,
): MessageSummary {
    const isMine = message.sender.id === viewer.id;
    const isStaff =
        viewer.role === 'moderator' || viewer.role === 'administrator';

    return {
        id: message.id,
        body: message.body,
        is_deleted: message.is_deleted,
        created_at: message.created_at,
        sender: message.sender,
        is_mine: isMine,
        can_delete: !message.is_deleted && (isMine || isStaff),
        can_report: !message.is_deleted && !isMine,
        reactions: (message.reactions ?? []).map((reaction) => ({
            emoji: reaction.emoji,
            count: reaction.count,
            reacted: reaction.user_ids.includes(viewer.id),
        })),
    };
}

/**
 * Tells the server whether the user is looking at the conversation, so it
 * holds back pushes for it.
 */
function sendPresence(conversationId: number, viewing: boolean): void {
    requestJson(
        presence(conversationId),
        { viewing },
        { keepalive: true },
    ).catch(() => {
        // The server drops a stale viewer by itself after 45 seconds.
    });
}

/**
 * Reports the user gone as the page itself goes away (tab closed, a full
 * page load elsewhere). Requests from a document that is unloading can be
 * dropped — every one of them when a service worker controls it — so the
 * worker sends it; without one, a beacon.
 */
function sendLeaving(conversationId: number): void {
    const endpoint = presence(conversationId);

    if (postFromServiceWorker(endpoint.url, { viewing: false })) {
        return;
    }

    if (typeof navigator.sendBeacon === 'function') {
        const body = new URLSearchParams({ viewing: '0' });
        const token = document.querySelector<HTMLMetaElement>(
            'meta[name="csrf-token"]',
        )?.content;

        if (token) {
            body.set('_token', token);
        }

        if (navigator.sendBeacon(endpoint.url, body)) {
            return;
        }
    }

    sendPresence(conversationId, false);
}

/**
 * The live side of a conversation page. From mount to unmount it:
 * - subscribes to `conversations.{id}` and hands new and changed messages,
 *   already shaped for this viewer, to the callbacks;
 * - catches up on messages after `lastMessageId()` whenever the
 *   subscription (re)starts — the first time and after every reconnect —
 *   and every 15 seconds while the WebSocket is down;
 * - reports the user as viewing it every 25 seconds while the tab is
 *   visible (pushes for it are held back), and as gone when hidden, left
 *   or closed;
 * - clears the conversation's notifications while it is on screen;
 * - tracks who else is typing.
 *
 * `conversationId` may be a ref or getter; changing it moves everything
 * over. Call from a component's setup.
 *
 * - `whisperTyping()`: call on input; sends a typing whisper at most every
 *   3 seconds.
 * - `typingUsers`: others typing, each dropped 5 seconds after their last
 *   whisper or as soon as their message arrives.
 * - `markRead()`: call when new messages from others arrive (or the page
 *   opens); it marks the conversation read now if the tab is visible, or
 *   as soon as it becomes visible.
 */
export function useConversationChannel(
    conversationId: MaybeRefOrGetter<number>,
    options: ConversationChannelOptions = {},
): {
    typingUsers: DeepReadonly<Ref<TypingUser[]>>;
    whisperTyping: () => void;
    markRead: () => void;
} {
    const page = usePage();
    const typingUsers = ref<TypingUser[]>([]);
    const typingTimers = new Map<number, ReturnType<typeof setTimeout>>();

    let current: number | null = null;
    let joined: { channel: PrivateChannel; release: () => void } | null = null;
    // Messages and reads come on the user's own channel, which only the
    // conversation's participants get (see ConversationParticipant::channelsFor).
    let mine: { channel: PrivateChannel; release: () => void } | null = null;
    let stopSubscribed: (() => void) | null = null;
    let stopPush: (() => void) | null = null;
    let lastDeliveredId = 0;
    let lastWhisperAt = 0;
    let viewing = false;
    let heartbeat: ReturnType<typeof setInterval> | undefined;
    let readInFlight = false;
    let readAgain = false;
    let readWhenVisible = false;
    let recovering = false;
    let recoverAgain = false;
    let fallbackPoll: ReturnType<typeof setInterval> | undefined;
    let stopWatchingConnection: (() => void) | null = null;

    const viewer = (): Viewer => page.props.auth.user;

    function stopTyping(userId: number): void {
        clearTimeout(typingTimers.get(userId));
        typingTimers.delete(userId);

        if (typingUsers.value.some((user) => user.id === userId)) {
            typingUsers.value = typingUsers.value.filter(
                (user) => user.id !== userId,
            );
        }
    }

    function startTyping(user: TypingUser): void {
        // Seeing others type goes with showing your own typing.
        if (!viewer().share_typing) {
            return;
        }

        clearTimeout(typingTimers.get(user.id));
        typingTimers.set(
            user.id,
            setTimeout(() => stopTyping(user.id), TYPING_SHOWN_FOR_MS),
        );

        if (!typingUsers.value.some((typing) => typing.id === user.id)) {
            typingUsers.value = [
                ...typingUsers.value,
                { id: user.id, name: user.name },
            ];
        }
    }

    function deliver(message: MessageSummary): void {
        lastDeliveredId = Math.max(lastDeliveredId, message.id);
        stopTyping(message.sender.id);
        options.onMessage?.(message);
    }

    const handleSent = (payload: { message: BroadcastMessage }): void => {
        if (payload.message.conversation_id === current) {
            deliver(toMessageSummary(payload.message, viewer()));
        }
    };

    const handleUpdated = (payload: { message: BroadcastMessage }): void => {
        if (payload.message.conversation_id === current) {
            options.onUpdate?.(toMessageSummary(payload.message, viewer()));
        }
    };

    const handleRead = (payload: {
        conversation_id: number;
        user_id: number;
        read_at: string | null;
    }): void => {
        if (payload.conversation_id === current) {
            options.onRead?.(payload.user_id, payload.read_at);
        }
    };

    const handleParticipants = (): void => {
        options.onParticipants?.();
    };

    const handleTyping = (user: TypingUser | undefined): void => {
        if (!user || typeof user.id !== 'number' || user.id === viewer().id) {
            return;
        }

        startTyping(user);
        options.onTyping?.(user);
    };

    /**
     * Fetches whatever arrived after the newest message the page holds.
     * Calls during one under way are folded into one more after it.
     */
    async function recover(): Promise<void> {
        if (recovering) {
            recoverAgain = true;

            return;
        }

        recovering = true;

        try {
            do {
                recoverAgain = false;

                const id = current;
                const known = options.lastMessageId
                    ? (options.lastMessageId() ?? 0)
                    : null;

                if (id === null || (known === null && lastDeliveredId === 0)) {
                    return;
                }

                const data = await requestJson<{
                    messages?: MessageSummary[];
                }>(
                    conversationMessages(id, {
                        query: { after: Math.max(known ?? 0, lastDeliveredId) },
                    }),
                );

                if (id === current) {
                    (data?.messages ?? []).forEach(deliver);
                }
            } while (recoverAgain);
        } catch {
            // The next broadcast, reconnect or visit catches up instead.
        } finally {
            recovering = false;
        }
    }

    function startFallbackPoll(): void {
        fallbackPoll ??= setInterval(() => {
            if (document.visibilityState === 'visible') {
                void recover();
            }
        }, FALLBACK_POLL_MS);
    }

    function stopFallbackPoll(): void {
        clearInterval(fallbackPoll);
        fallbackPoll = undefined;
    }

    function startViewing(): void {
        if (
            current === null ||
            viewing ||
            document.visibilityState !== 'visible'
        ) {
            return;
        }

        const id = current;

        viewing = true;
        viewed.value = id;
        sendPresence(id, true);
        heartbeat = setInterval(() => {
            if (document.visibilityState === 'visible') {
                sendPresence(id, true);
            }
        }, PRESENCE_HEARTBEAT_MS);
        clearNotifications(`conversation-${id}`);

        if (readWhenVisible) {
            readWhenVisible = false;
            markRead();
        }
    }

    function stopViewing(leaving = false): void {
        if (!viewing || current === null) {
            return;
        }

        viewing = false;

        if (viewed.value === current) {
            viewed.value = null;
        }

        clearInterval(heartbeat);
        heartbeat = undefined;

        if (leaving) {
            sendLeaving(current);
        } else {
            sendPresence(current, false);
        }
    }

    function markRead(): void {
        // Before mount (or on the server): do it once the page is on screen.
        if (current === null) {
            readWhenVisible = true;

            return;
        }

        if (document.visibilityState !== 'visible') {
            readWhenVisible = true;

            return;
        }

        if (readInFlight) {
            readAgain = true;

            return;
        }

        readInFlight = true;
        clearNotifications(`conversation-${current}`);

        requestJson(read(current))
            .catch(() => {
                // Still unread; the next markRead() or visit tries again.
            })
            .finally(() => {
                readInFlight = false;

                if (readAgain) {
                    readAgain = false;
                    markRead();
                }
            });
    }

    function whisperTyping(): void {
        const now = Date.now();
        const me = viewer();

        // Showing that you type is a setting (on unless turned off).
        if (
            !me.share_typing ||
            !joined?.channel.subscription.subscribed ||
            now - lastWhisperAt < TYPING_WHISPER_EVERY_MS
        ) {
            return;
        }

        lastWhisperAt = now;

        try {
            joined.channel.whisper('typing', { id: me.id, name: me.name });
        } catch {
            // Not subscribed after all; the next keystroke tries again.
        }
    }

    function connect(id: number): void {
        current = id;
        lastDeliveredId = 0;
        lastWhisperAt = 0;
        joined = joinPrivateChannel(`conversations.${id}`);

        if (joined) {
            joined.channel
                .listen('.participants.updated', handleParticipants)
                .listenForWhisper('typing', handleTyping);
            stopSubscribed = onSubscribed(joined.channel, () => {
                void recover();
            });
        }

        mine = joinPrivateChannel(`App.Models.User.${viewer().id}`);
        mine?.channel
            .listen('.message.sent', handleSent)
            .listen('.message.updated', handleUpdated)
            .listen('.conversation.read', handleRead);

        startViewing();
    }

    function disconnect(): void {
        stopViewing();
        stopSubscribed?.();
        stopSubscribed = null;

        if (joined) {
            joined.channel
                .stopListening('.participants.updated', handleParticipants)
                .stopListeningForWhisper('typing', handleTyping);
            joined.release();
            joined = null;
        }

        if (mine) {
            mine.channel
                .stopListening('.message.sent', handleSent)
                .stopListening('.message.updated', handleUpdated)
                .stopListening('.conversation.read', handleRead);
            mine.release();
            mine = null;
        }

        typingTimers.forEach((timer) => clearTimeout(timer));
        typingTimers.clear();
        typingUsers.value = [];
        current = null;
        readAgain = false;
        readWhenVisible = false;
    }

    const onVisibilityChange = (): void => {
        if (document.visibilityState === 'visible') {
            startViewing();
        } else {
            stopViewing();
        }
    };

    // Closing a tab hides it first, and that report may not have made it out
    // of the closing document: say it again the reliable way.
    const onPageHide = (): void => {
        if (viewing) {
            stopViewing(true);
        } else if (current !== null) {
            sendLeaving(current);
        }
    };

    const onPageShow = (event: PageTransitionEvent): void => {
        if (event.persisted) {
            startViewing();
        }
    };

    onMounted(() => {
        document.addEventListener('visibilitychange', onVisibilityChange);
        window.addEventListener('pagehide', onPageHide);
        window.addEventListener('pageshow', onPageShow);

        // The WebSocket may be down while a push for this conversation still
        // arrives (the service worker hands it to the page it is about).
        stopPush = onPushMessage((data) => {
            if (
                data.type === 'message' &&
                current !== null &&
                Number(data.conversation_id) === current
            ) {
                void recover();
            }
        });

        // Without the WebSocket nothing is broadcast: ask now and then.
        stopWatchingConnection = watch(
            connectionState,
            (state) => {
                if (state === 'disconnected') {
                    startFallbackPoll();
                } else {
                    stopFallbackPoll();
                }
            },
            { immediate: true },
        );

        connect(toValue(conversationId));
    });

    watch(
        () => toValue(conversationId),
        (id) => {
            if (current !== null && id !== current) {
                disconnect();
                connect(id);
            }
        },
    );

    onBeforeUnmount(() => {
        document.removeEventListener('visibilitychange', onVisibilityChange);
        window.removeEventListener('pagehide', onPageHide);
        window.removeEventListener('pageshow', onPageShow);
        stopPush?.();
        stopWatchingConnection?.();
        stopFallbackPoll();
        disconnect();
    });

    return {
        typingUsers: readonly(typingUsers),
        whisperTyping,
        markRead,
    };
}
